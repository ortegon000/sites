<?php

use App\Enums\ChargeStatus;
use App\Livewire\ChargesPanel;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

function chargeFor(Project $project, string $amount = '24000.00'): Charge
{
    $service = Service::factory()->monthly()->for($project)->create();

    return Charge::factory()->for($service)->pending()->create(['amount' => $amount]);
}

test('un abono parcial deja el cobro en parcial y muestra el restante', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->for(Client::factory()->client())->create();
    $charge = chargeFor($project);

    $this->actingAs($staff);

    Livewire::test(ChargesPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('openPaymentsModal', $charge->id)
        ->set('paymentAmount', '12914.00')
        ->set('paymentPaidOn', today()->toDateString())
        ->set('paymentMethod', 'Transferencia')
        ->set('paymentInvoiceReference', 'A-1042')
        ->call('savePayment')
        ->assertHasNoErrors();

    $charge->refresh();

    expect($charge->status)->toBe(ChargeStatus::Parcial)
        ->and($charge->paidAmount())->toBe(12914.0)
        ->and($charge->remainingAmount())->toBe(11086.0)
        ->and($charge->paid_at)->toBeNull()
        ->and($charge->payments()->first()->invoice_reference)->toBe('A-1042');
});

test('abonar el resto deja el cobro pagado con la fecha del último abono', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->for(Client::factory()->client())->create();
    $charge = chargeFor($project, '3000.00');

    $this->actingAs($staff);

    $component = Livewire::test(ChargesPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('openPaymentsModal', $charge->id)
        ->set('paymentAmount', '1000.00')
        ->set('paymentPaidOn', today()->subDays(10)->toDateString())
        ->call('savePayment');

    /** El formulario se reinicia con el restante, así que el segundo abono ya viene precargado. */
    expect($component->get('paymentAmount'))->toBe('2000');

    $component->set('paymentPaidOn', today()->subDays(2)->toDateString())
        ->call('savePayment')
        ->assertHasNoErrors();

    $charge->refresh();

    expect($charge->status)->toBe(ChargeStatus::Pagado)
        ->and($charge->remainingAmount())->toBe(0.0)
        ->and($charge->paid_at->toDateString())->toBe(today()->subDays(2)->toDateString());
});

test('eliminar un abono devuelve el cobro a pendiente', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->for(Client::factory()->client())->create();
    $charge = chargeFor($project, '5000.00');

    $this->actingAs($staff);

    Livewire::test(ChargesPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('openPaymentsModal', $charge->id)
        ->set('paymentAmount', '5000.00')
        ->set('paymentPaidOn', today()->toDateString())
        ->call('savePayment')
        ->call('deletePayment', $charge->payments()->firstOrFail()->id)
        ->assertHasNoErrors();

    $charge->refresh();

    expect($charge->status)->toBe(ChargeStatus::Pendiente)
        ->and($charge->paid_at)->toBeNull()
        ->and($charge->payments()->count())->toBe(0);
});

test('marcar pagado registra el restante como un abono', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->for(Client::factory()->client())->create();
    $charge = chargeFor($project, '2000.00');
    $charge->payments()->create(['amount' => '500.00', 'paid_on' => today()->subDay()->toDateString()]);
    $charge->syncStatusFromPayments();

    $this->actingAs($staff);

    Livewire::test(ChargesPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('markChargeAsPaid', $charge->id)
        ->assertHasNoErrors();

    $charge->refresh();

    expect($charge->status)->toBe(ChargeStatus::Pagado)
        ->and($charge->payments()->count())->toBe(2)
        ->and((float) $charge->payments()->orderByDesc('id')->first()->amount)->toBe(1500.0);
});

test('un cobro parcial que vence se marca vencido y conserva su abono', function () {
    $project = Project::factory()->for(Client::factory()->client())->create();
    $service = Service::factory()->monthly()->for($project)->create(['next_charge_date' => null]);

    $charge = Charge::factory()->for($service)->create([
        'status' => ChargeStatus::Pendiente,
        'due_date' => today()->subDay()->toDateString(),
        'amount' => '1000.00',
    ]);

    $charge->payments()->create(['amount' => '400.00', 'paid_on' => today()->subDays(3)->toDateString()]);
    $charge->syncStatusFromPayments();

    expect($charge->status)->toBe(ChargeStatus::Vencido);

    $this->artisan('charges:process')->assertSuccessful();

    $charge->refresh();

    expect($charge->status)->toBe(ChargeStatus::Vencido)
        ->and($charge->remainingAmount())->toBe(600.0);
});

test('staff puede editar el concepto, el monto y la fecha de un cobro', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->for(Client::factory()->client())->create();
    $charge = chargeFor($project, '5500.00');

    $this->actingAs($staff);

    Livewire::test(ChargesPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('openChargeModal', $charge->id)
        ->set('chargeConcept', 'Mantenimiento — 20 horas')
        ->set('chargeAmount', '9500.00')
        ->set('chargeDueDate', today()->addDays(20)->toDateString())
        ->call('saveCharge')
        ->assertHasNoErrors();

    $charge->refresh();

    expect($charge->concept)->toBe('Mantenimiento — 20 horas')
        ->and($charge->conceptLabel())->toBe('Mantenimiento — 20 horas')
        ->and((float) $charge->amount)->toBe(9500.0)
        ->and($charge->due_date->toDateString())->toBe(today()->addDays(20)->toDateString());
});

test('bajar el monto de un cobro por debajo de lo abonado lo deja pagado', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->for(Client::factory()->client())->create();
    $charge = chargeFor($project, '5000.00');
    $charge->payments()->create(['amount' => '3000.00', 'paid_on' => today()->toDateString()]);
    $charge->syncStatusFromPayments();

    $this->actingAs($staff);

    Livewire::test(ChargesPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('openChargeModal', $charge->id)
        ->set('chargeAmount', '3000.00')
        ->call('saveCharge')
        ->assertHasNoErrors();

    expect($charge->refresh()->status)->toBe(ChargeStatus::Pagado);
});

test('no se puede abonar a un cobro de otro proyecto', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->for(Client::factory()->client())->create();
    $otherCharge = chargeFor(Project::factory()->for(Client::factory()->client())->create());

    $this->actingAs($staff);

    expect(fn () => Livewire::test(ChargesPanel::class, ['client' => $project->client, 'project' => $project])->call('openPaymentsModal', $otherCharge->id))
        ->toThrow(ModelNotFoundException::class);
});

test('un colaborador no puede ver ni registrar abonos', function () {
    $collaborator = User::factory()->collaborator()->create();
    $project = Project::factory()->for(Client::factory()->client())->create();
    $project->users()->attach($collaborator);
    $charge = chargeFor($project, '1000.00');

    $this->actingAs($collaborator);

    Livewire::test(ChargesPanel::class, ['client' => $project->client, 'project' => $project])
        ->assertForbidden();

    expect($charge->payments()->count())->toBe(0);
});
