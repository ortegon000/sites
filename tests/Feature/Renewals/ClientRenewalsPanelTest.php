<?php

use App\Enums\RenewalStatus;
use App\Livewire\RenewalsPanel;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Renewal;
use App\Models\User;
use App\Notifications\RenewalNoticeNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('la ficha del cliente separa lo que sigue abierto de lo ya decidido', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $porVencer = Domain::factory()->for($client)->create(['name' => 'porvencer.com']);
    $yaRenovado = Domain::factory()->for($client)->create(['name' => 'yarenovado.com']);

    Renewal::factory()->for($client)->create([
        'renewable_type' => Domain::class,
        'renewable_id' => $porVencer->id,
    ]);
    Renewal::factory()->for($client)->create([
        'renewable_type' => Domain::class,
        'renewable_id' => $yaRenovado->id,
        'status' => RenewalStatus::Renovado,
    ]);

    $this->actingAs($staff);

    Livewire::test(RenewalsPanel::class, ['client' => $client])
        ->assertSee('porvencer.com')
        ->assertDontSee('yarenovado.com')
        ->set('renewalsTab', 'historial')
        ->assertSee('yarenovado.com')
        ->assertDontSee('porvencer.com');
});

test('la tarjeta solo muestra los ciclos de este cliente', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $propio = Domain::factory()->for($client)->create(['name' => 'propio.com']);
    Renewal::factory()->for($client)->create([
        'renewable_type' => Domain::class,
        'renewable_id' => $propio->id,
    ]);

    Renewal::factory()->create();

    $this->actingAs($staff);

    Livewire::test(RenewalsPanel::class, ['client' => $client])
        ->assertSee('propio.com');

    expect(Livewire::test(RenewalsPanel::class, ['client' => $client])->get('renewals'))->toHaveCount(1);
});

test('no se puede cerrar el ciclo de otro cliente desde esta tarjeta', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $ajena = Renewal::factory()->create();

    $this->actingAs($staff);

    expect(fn () => Livewire::test(RenewalsPanel::class, ['client' => $client])->call('markRenewed', $ajena->id))
        ->toThrow(ModelNotFoundException::class);
});

test('desde la ficha se avisa al cliente y el ciclo queda avisado', function () {
    Notification::fake();

    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $contact = Contact::factory()->create(['email' => 'dueno@ejemplo.test']);
    $client->contacts()->attach($contact, ['is_primary' => true]);

    $renewal = Renewal::factory()->for($client)->create();

    $this->actingAs($staff);

    Livewire::test(RenewalsPanel::class, ['client' => $client])
        ->call('notifyClient', $renewal->id);

    Notification::assertSentOnDemand(RenewalNoticeNotification::class);

    expect($renewal->refresh()->status)->toBe(RenewalStatus::Avisado);
});

test('el costo capturado desde la ficha es el que se le cobra al renovar', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $renewal = Renewal::factory()->for($client)->create(['amount' => null]);

    $this->actingAs($staff);

    Livewire::test(RenewalsPanel::class, ['client' => $client])
        ->call('openAmountModal', $renewal->id)
        ->set('renewalAmount', '850')
        ->set('renewalNotes', 'Sube el precio del registrador.')
        ->call('saveRenewal')
        ->assertHasNoErrors();

    expect((float) $renewal->refresh()->amount)->toBe(850.0)
        ->and($renewal->notes)->toBe('Sube el precio del registrador.');
});
