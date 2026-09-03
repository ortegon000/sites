<?php

use App\Actions\Renewals\MarkRenewalNotRenewed;
use App\Actions\Renewals\MarkRenewalRenewed;
use App\Actions\Renewals\NotifyClientOfRenewal;
use App\Enums\DomainManagement;
use App\Enums\DomainStatus;
use App\Enums\LicenseStatus;
use App\Enums\RenewalStatus;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\License;
use App\Models\Renewal;
use App\Models\Service;
use App\Models\User;
use App\Notifications\RenewalNoticeNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('la corrida diaria abre un ciclo por dominio, licencia y servicio anual que caduca, y no los duplica', function () {
    Notification::fake();

    $client = Client::factory()->client()->create();
    $client->contacts()->attach(Contact::factory()->create(['email' => 'dueno@cliente.test']), ['is_primary' => true]);

    $domain = Domain::factory()->for($client)->create([
        'management' => DomainManagement::Managed,
        'status' => DomainStatus::Activo,
        'expires_at' => today()->addDays(25)->toDateString(),
    ]);

    $license = License::factory()->for($client)->create([
        'renewal_date' => today()->addDays(40)->toDateString(),
        'cost' => '2400.00',
    ]);

    $service = Service::factory()->standalone()->for($client)->annual()->create([
        'next_charge_date' => today()->addDays(50)->toDateString(),
        'amount' => '4000.00',
    ]);

    /** Fuera del horizonte: todavía no es asunto de nadie. */
    Domain::factory()->for($client)->create([
        'management' => DomainManagement::Managed,
        'status' => DomainStatus::Activo,
        'expires_at' => today()->addDays(200)->toDateString(),
    ]);

    /** De solo seguimiento: lo renueva su dueño, no nosotros. */
    Domain::factory()->for($client)->create([
        'management' => DomainManagement::Tracked,
        'status' => DomainStatus::Activo,
        'expires_at' => today()->addDays(10)->toDateString(),
    ]);

    $this->artisan('charges:process')->assertSuccessful();
    $this->artisan('charges:process')->assertSuccessful();

    expect(Renewal::count())->toBe(3)
        /** Solo el que cae en la ventana de un mes se avisa; los otros quedan en el radar. */
        ->and($domain->renewals()->first()->status)->toBe(RenewalStatus::Avisado)
        ->and($license->renewals()->first()->status)->toBe(RenewalStatus::PorAvisar)
        ->and((float) $license->renewals()->first()->amount)->toBe(2400.0)
        ->and((float) $service->renewals()->first()->amount)->toBe(4000.0);

    Notification::assertSentOnDemandTimes(RenewalNoticeNotification::class, 1);
});

test('el aviso va a los contactos del cliente con correo y deja constancia', function () {
    Notification::fake();

    $client = Client::factory()->client()->create();
    $contact = Contact::factory()->create(['email' => 'dueno@cliente.test']);
    $client->contacts()->attach($contact, ['is_primary' => true]);

    $renewal = Renewal::factory()->create(['client_id' => $client->id]);

    expect(app(NotifyClientOfRenewal::class)->handle($renewal))->toBeTrue();

    $renewal->refresh();

    expect($renewal->status)->toBe(RenewalStatus::Avisado)
        ->and($renewal->notified_at)->not->toBeNull();

    Notification::assertSentOnDemand(RenewalNoticeNotification::class);
});

test('sin contacto con correo el ciclo se queda por avisar, en vez de darse por avisado', function () {
    Notification::fake();

    $client = Client::factory()->client()->create();
    $renewal = Renewal::factory()->create(['client_id' => $client->id]);

    expect(app(NotifyClientOfRenewal::class)->handle($renewal))->toBeFalse()
        ->and($renewal->fresh()->status)->toBe(RenewalStatus::PorAvisar);

    Notification::assertNothingSent();
});

test('el correo al cliente lleva enlace al portal y ninguna credencial', function () {
    $client = Client::factory()->client()->create();
    $domain = Domain::factory()->for($client)->create(['name' => 'clinica-sur.test']);

    $renewal = Renewal::factory()->create([
        'client_id' => $client->id,
        'renewable_type' => Domain::class,
        'renewable_id' => $domain->id,
        'amount' => '4000.00',
    ]);

    $mail = (new RenewalNoticeNotification($renewal))->toMail(new stdClass);
    $rendered = (string) $mail->render();

    expect($mail->actionUrl)->toBe(route('portal.renewals.index'))
        ->and($rendered)->toContain('clinica-sur.test')
        ->and($rendered)->not->toContain('contraseña');
});

test('registrar que renovó empuja la fecha un año y genera la línea cobrable', function () {
    $client = Client::factory()->client()->create();
    $domain = Domain::factory()->for($client)->create([
        'expires_at' => today()->addDays(20)->toDateString(),
    ]);

    $renewal = Renewal::factory()->create([
        'client_id' => $client->id,
        'renewable_type' => Domain::class,
        'renewable_id' => $domain->id,
        'due_date' => $domain->expires_at->toDateString(),
        'amount' => '4000.00',
    ]);

    app(MarkRenewalRenewed::class)->handle($renewal);

    $renewal->refresh();
    $domain->refresh();

    expect($renewal->status)->toBe(RenewalStatus::Renovado)
        ->and($domain->expires_at->toDateString())->toBe(today()->addDays(20)->addYear()->toDateString())
        ->and($renewal->service)->not->toBeNull()
        ->and($renewal->service->project_id)->toBeNull()
        ->and((float) $renewal->service->amount)->toBe(4000.0)
        ->and($renewal->service->domain_id)->toBe($domain->id);
});

test('renovar un servicio anual no genera línea: ya se cobra por su calendario', function () {
    $client = Client::factory()->client()->create();
    $service = Service::factory()->standalone()->for($client)->annual()->create([
        'next_charge_date' => today()->addDays(30)->toDateString(),
    ]);

    $renewal = Renewal::factory()->create([
        'client_id' => $client->id,
        'renewable_type' => Service::class,
        'renewable_id' => $service->id,
        'due_date' => today()->addDays(30)->toDateString(),
        'amount' => '4000.00',
    ]);

    app(MarkRenewalRenewed::class)->handle($renewal);

    expect($renewal->fresh()->service_id)->toBeNull()
        ->and($client->services()->count())->toBe(1);
});

test('registrar que no renovó da de baja lo que caducaba', function () {
    $client = Client::factory()->client()->create();

    $domain = Domain::factory()->for($client)->create(['status' => DomainStatus::Activo, 'auto_renew' => true]);
    $license = License::factory()->for($client)->create(['status' => LicenseStatus::Activa]);
    $service = Service::factory()->standalone()->for($client)->annual()->create();

    foreach ([[Domain::class, $domain->id], [License::class, $license->id], [Service::class, $service->id]] as [$type, $id]) {
        app(MarkRenewalNotRenewed::class)->handle(Renewal::factory()->create([
            'client_id' => $client->id,
            'renewable_type' => $type,
            'renewable_id' => $id,
        ]));
    }

    expect($domain->fresh()->status)->toBe(DomainStatus::Expirado)
        ->and($domain->fresh()->auto_renew)->toBeFalse()
        ->and($license->fresh()->status)->toBe(LicenseStatus::Cancelada)
        ->and($service->fresh()->status)->toBe(ServiceStatus::Cancelado)
        ->and($service->fresh()->next_charge_date)->toBeNull();
});

test('el tablero lista lo que caduca y permite avisar y cerrar el ciclo', function () {
    Notification::fake();

    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create(['name' => 'Clínica Sur']);
    $contact = Contact::factory()->create(['email' => 'dueno@clinica.test']);
    $client->contacts()->attach($contact, ['is_primary' => true]);

    $domain = Domain::factory()->for($client)->create([
        'name' => 'clinica-sur.test',
        'expires_at' => today()->addDays(15)->toDateString(),
    ]);

    $renewal = Renewal::factory()->create([
        'client_id' => $client->id,
        'renewable_type' => Domain::class,
        'renewable_id' => $domain->id,
        'due_date' => today()->addDays(15)->toDateString(),
    ]);

    $this->actingAs($staff);

    Livewire::test('pages::renewals.index')
        ->assertSee('clinica-sur.test')
        ->call('openAmountModal', $renewal->id)
        ->set('renewalAmount', '4000')
        ->call('saveRenewal')
        ->call('notifyClient', $renewal->id)
        ->call('markRenewed', $renewal->id);

    $renewal->refresh();

    expect($renewal->status)->toBe(RenewalStatus::Renovado)
        ->and($renewal->notified_at)->not->toBeNull()
        ->and((float) $renewal->service->amount)->toBe(4000.0);
});

test('un colaborador no entra al tablero de renovaciones', function () {
    $collaborator = User::factory()->collaborator()->create();

    $this->actingAs($collaborator);

    $this->get(route('renewals.index'))->assertForbidden();
});

test('el portal muestra las renovaciones abiertas del cliente y no las de otro', function () {
    $client = Client::factory()->client()->create();
    $otherClient = Client::factory()->client()->create();

    $contact = Contact::factory()->create();
    $client->contacts()->attach($contact, ['is_primary' => true]);
    $clientUser = User::factory()->client()->create(['contact_id' => $contact->id]);

    $ownDomain = Domain::factory()->for($client)->create(['name' => 'mi-dominio.test']);
    $foreignDomain = Domain::factory()->for($otherClient)->create(['name' => 'ajeno.test']);

    Renewal::factory()->create([
        'client_id' => $client->id,
        'renewable_type' => Domain::class,
        'renewable_id' => $ownDomain->id,
    ]);

    Renewal::factory()->create([
        'client_id' => $otherClient->id,
        'renewable_type' => Domain::class,
        'renewable_id' => $foreignDomain->id,
    ]);

    $this->actingAs($clientUser);

    $this->get(route('portal.renewals.index'))
        ->assertOk()
        ->assertSee('mi-dominio.test')
        ->assertDontSee('ajeno.test');
});
