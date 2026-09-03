<?php

use App\Enums\ChargeStatus;
use App\Enums\ServiceBillingFrequency;
use App\Livewire\ChargesPanel;
use App\Livewire\ServicesPanel;
use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('la captura rápida crea una línea del cliente sin proyecto y su cobro', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(ServicesPanel::class, ['client' => $client])
        ->set('quickName', 'Renovación anual de hosting')
        ->set('quickAmount', '4000')
        ->set('quickStartsOn', today()->toDateString())
        ->call('quickCapture')
        ->assertHasNoErrors();

    $service = $client->services()->firstOrFail();

    expect($service->project_id)->toBeNull()
        ->and($service->billing_frequency)->toBe(ServiceBillingFrequency::OneTime)
        ->and((float) $service->amount)->toBe(4000.0)
        ->and($service->charges()->count())->toBe(1)
        ->and($service->charges()->first()->status)->toBe(ChargeStatus::Pendiente);
});

test('la captura rápida admite una frecuencia recurrente y programa el siguiente cobro', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(ServicesPanel::class, ['client' => $client])
        ->set('quickName', 'Soporte quincenal')
        ->set('quickAmount', '2500')
        ->set('quickStartsOn', today()->toDateString())
        ->set('quickFrequency', ServiceBillingFrequency::Biweekly->value)
        ->call('quickCapture')
        ->assertHasNoErrors();

    $service = $client->services()->firstOrFail();

    expect($service->next_charge_date->toDateString())
        ->toBe(ServiceBillingFrequency::Biweekly->advanceFrom(today()->toImmutable())->toDateString());
});

test('el panel del cliente solo lista sus líneas sueltas, no las de sus proyectos', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();

    Service::factory()->standalone()->for($client)->create(['name' => 'Renovación anual']);
    Service::factory()->for($project)->create(['name' => 'Desarrollo del sitio']);

    $this->actingAs($staff);

    Livewire::test(ServicesPanel::class, ['client' => $client])
        ->assertSee('Renovación anual')
        ->assertDontSee('Desarrollo del sitio');
});

test('el estado de cuenta del cliente incluye los cobros de sus líneas sueltas y los de sus proyectos', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();

    $loose = Service::factory()->standalone()->for($client)->create(['name' => 'Renovación anual']);
    $loose->charges()->create([
        'amount' => '4000.00',
        'currency' => 'MXN',
        'status' => ChargeStatus::Pendiente,
        'due_date' => today()->addDays(5)->toDateString(),
    ]);

    $inProject = Service::factory()->for($project)->create(['name' => 'Desarrollo del sitio']);
    $inProject->charges()->create([
        'amount' => '20000.00',
        'currency' => 'MXN',
        'status' => ChargeStatus::Pendiente,
        'due_date' => today()->addDays(9)->toDateString(),
    ]);

    $this->actingAs($staff);

    Livewire::test(ChargesPanel::class, ['client' => $client])
        ->assertSee('Renovación anual')
        ->assertSee('Desarrollo del sitio')
        ->assertSee('Línea suelta');
});

test('se puede abonar al cobro de una línea suelta desde la ficha del cliente', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $service = Service::factory()->standalone()->for($client)->create();
    $charge = $service->charges()->create([
        'amount' => '1000.00',
        'currency' => 'MXN',
        'status' => ChargeStatus::Pendiente,
        'due_date' => today()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($staff);

    Livewire::test(ChargesPanel::class, ['client' => $client])
        ->call('openPaymentsModal', $charge->id)
        ->set('paymentAmount', '400')
        ->set('paymentPaidOn', today()->toDateString())
        ->call('savePayment')
        ->assertHasNoErrors();

    expect($charge->fresh()->status)->toBe(ChargeStatus::Parcial)
        ->and($charge->fresh()->remainingAmount())->toBe(600.0);
});

test('las subtareas se agregan, se marcan hechas y no cambian el monto del servicio', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $service = Service::factory()->standalone()->for($client)->create(['amount' => '1000.00']);

    $this->actingAs($staff);

    $component = Livewire::test(ServicesPanel::class, ['client' => $client])
        ->call('openItemsModal', $service->id)
        ->set('itemDescription', 'Primera visita')
        ->set('itemDueDate', today()->toDateString())
        ->call('addItem')
        ->assertHasNoErrors();

    $item = $service->items()->firstOrFail();

    expect($item->isDone())->toBeFalse();

    $component->call('toggleItem', $item->id);

    expect($item->fresh()->isDone())->toBeTrue()
        ->and((float) $service->fresh()->amount)->toBe(1000.0);

    $component->call('deleteItem', $item->id);

    expect($service->items()->count())->toBe(0);
});

test('no se puede tocar la línea de otro cliente desde este panel', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $foreign = Service::factory()->standalone()->for(Client::factory()->client())->create();

    $this->actingAs($staff);

    expect(fn () => Livewire::test(ServicesPanel::class, ['client' => $client])->call('cancelService', $foreign->id))
        ->toThrow(ModelNotFoundException::class);
});

test('la lista de trabajos y cobros muestra líneas con y sin proyecto y filtra por cliente', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create(['name' => 'Clínica Sur']);
    $otherClient = Client::factory()->client()->create(['name' => 'Tacos El Güero']);
    $project = Project::factory()->for($client)->create(['name' => 'Sitio Clínica Sur']);

    Service::factory()->standalone()->for($client)->create(['name' => 'Renovación anual']);
    Service::factory()->for($project)->create(['name' => 'Desarrollo del sitio']);
    Service::factory()->standalone()->for($otherClient)->create(['name' => 'Menú digital']);

    $this->actingAs($staff);

    Livewire::test('pages::billables.index')
        ->assertSee('Renovación anual')
        ->assertSee('Desarrollo del sitio')
        ->assertSee('Menú digital')
        ->assertSee('Línea suelta')
        ->set('clientFilter', $client->id)
        ->assertSee('Renovación anual')
        ->assertDontSee('Menú digital');
});

test('un colaborador no entra a trabajos y cobros', function () {
    $collaborator = User::factory()->collaborator()->create();

    $this->actingAs($collaborator);

    $this->get(route('billables.index'))->assertForbidden();
});
