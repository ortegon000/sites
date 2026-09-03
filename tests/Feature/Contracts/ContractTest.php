<?php

use App\Actions\Contracts\DraftContract;
use App\Enums\ContractStatus;
use App\Livewire\ContractsPanel;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\User;
use Livewire\Livewire;

test('el contrato se genera con los servicios, montos y entregables que ya están capturados', function () {
    $client = Client::factory()->client()->create(['name' => 'Clínica Sur', 'company_name' => 'Servicios Médicos del Sur S.C.']);
    $client->contacts()->attach(Contact::factory()->create(['name' => 'Ana Ruiz']), ['is_primary' => true]);

    $hosting = Service::factory()->standalone()->for($client)->annual()->create([
        'name' => 'Hosting anual',
        'amount' => '4000.00',
    ]);

    $maintenance = Service::factory()->standalone()->for($client)->oneTime()->create([
        'name' => 'Rediseño del sitio',
        'amount' => '20000.00',
    ]);

    ServiceItem::factory()->for($maintenance)->create([
        'description' => 'Entrega de maquetas',
        'due_date' => today()->addDays(20)->toDateString(),
    ]);

    $contract = app(DraftContract::class)->handle(
        $client,
        $client->services()->with('items')->get(),
        'Contrato de servicios web',
        today()->toDateString(),
        today()->addYear()->toDateString(),
    );

    expect($contract->number)->toStartWith('CT-'.today()->year.'-')
        ->and($contract->status)->toBe(ContractStatus::Borrador)
        ->and($contract->services()->count())->toBe(2)
        ->and($contract->body)
        ->toContain('Servicios Médicos del Sur S.C.')
        ->toContain('Ana Ruiz')
        ->toContain('Hosting anual — Anual — 4,000.00 MXN')
        ->toContain('Entrega de maquetas')
        /** Los totales se separan: lo recurrente por periodo y lo de una sola vez aparte. */
        ->toContain('4,000.00 MXN por periodo')
        ->toContain('20,000.00 MXN');

    expect($contract->body)->not->toContain('@if')
        ->and($contract->body)->not->toContain('@endif');
});

test('los folios son consecutivos por año', function () {
    $client = Client::factory()->client()->create();

    $first = Contract::factory()->for($client)->create();
    $second = Contract::factory()->for($client)->create();

    expect($first->number)->toBe(sprintf('CT-%d-0001', today()->year))
        ->and($second->number)->toBe(sprintf('CT-%d-0002', today()->year));
});

test('staff genera un contrato desde la ficha del cliente eligiendo qué servicios ampara', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $covered = Service::factory()->standalone()->for($client)->annual()->create(['name' => 'Hosting anual']);
    $excluded = Service::factory()->standalone()->for($client)->monthly()->create(['name' => 'Ads mensual']);

    $this->actingAs($staff);

    Livewire::test(ContractsPanel::class, ['client' => $client])
        ->call('openDraftModal')
        ->set('selectedServices', [$covered->id])
        ->set('contractTitle', 'Contrato de hosting')
        ->set('contractStartsOn', today()->toDateString())
        ->call('draft')
        ->assertHasNoErrors();

    $contract = $client->contracts()->firstOrFail();

    expect($contract->services->pluck('id')->all())->toBe([$covered->id])
        ->and($contract->body)->toContain('Hosting anual')
        ->and($contract->body)->not->toContain($excluded->name);
});

test('un contrato firmado congela su texto', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $contract = Contract::factory()->for($client)->create(['body' => 'Texto original.']);

    $this->actingAs($staff);

    $component = Livewire::test(ContractsPanel::class, ['client' => $client])
        ->call('openSignModal', $contract->id)
        ->set('signedBy', 'Juan Pérez')
        ->call('sign');

    $contract->refresh();

    expect($contract->status)->toBe(ContractStatus::Firmado)
        ->and($contract->signed_by)->toBe('Juan Pérez')
        ->and($contract->signed_at)->not->toBeNull()
        ->and($contract->isEditable())->toBeFalse();

    $component->call('openBodyModal', $contract->id)
        ->set('contractBody', 'Texto cambiado a escondidas.')
        ->call('saveBody');

    expect($contract->fresh()->body)->toBe('Texto original.');
});

test('la versión imprimible muestra el texto y avisa cuando todavía no está firmado', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $contract = Contract::factory()->for($client)->create(['body' => 'PRIMERA. OBJETO']);

    $this->actingAs($staff);

    $this->get(route('contracts.print', $contract))
        ->assertOk()
        ->assertSee('PRIMERA. OBJETO')
        ->assertSee('todavía no está firmado', escape: false);

    $signed = Contract::factory()->for($client)->signed()->create(['body' => 'PRIMERA. OBJETO']);

    $this->get(route('contracts.print', $signed))
        ->assertOk()
        ->assertDontSee('todavía no está firmado', escape: false);
});

test('un colaborador no ve contratos ni su versión imprimible', function () {
    $collaborator = User::factory()->collaborator()->create();
    $contract = Contract::factory()->create();

    $this->actingAs($collaborator);

    $this->get(route('contracts.index'))->assertForbidden();
    $this->get(route('contracts.print', $contract))->assertForbidden();
});

test('el listado resume lo vigente, lo que espera firma y lo que termina pronto', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create(['name' => 'Clínica Sur']);

    Contract::factory()->for($client)->signed()->create(['title' => 'Contrato vigente', 'ends_on' => today()->addMonths(6)->toDateString()]);
    Contract::factory()->for($client)->signed()->create(['title' => 'Contrato por terminar', 'ends_on' => today()->addDays(30)->toDateString()]);
    Contract::factory()->for($client)->sent()->create(['title' => 'Contrato esperando firma']);

    $this->actingAs($staff);

    Livewire::test('pages::contracts.index')
        ->assertSee('Contrato vigente')
        ->assertSee('Contrato esperando firma')
        ->assertSeeHtml('>2<')
        ->set('statusFilter', ContractStatus::Enviado->value)
        ->assertSee('Contrato esperando firma')
        ->assertDontSee('Contrato vigente');
});
