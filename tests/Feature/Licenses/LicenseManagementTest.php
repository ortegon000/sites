<?php

use App\Enums\LicenseStatus;
use App\Livewire\ClientLicenses;
use App\Models\Client;
use App\Models\Domain;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('staff can register a license for a client', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(ClientLicenses::class, ['client' => $client])
        ->call('openLicenseModal')
        ->set('name', 'Brevo')
        ->set('vendor', 'Sendinblue')
        ->set('cost', '500')
        ->set('renewalDate', now()->addMonths(6)->toDateString())
        ->call('saveLicense')
        ->assertHasNoErrors();

    $license = $client->licenses()->firstOrFail();

    expect($license->name)->toBe('Brevo')
        ->and($license->domain_id)->toBeNull()
        ->and($license->status)->toBe(LicenseStatus::Activa);
});

test('a license can be tied to a domain of its own client only', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $ownDomain = Domain::factory()->for($client)->create();
    $foreignDomain = Domain::factory()->create();

    $this->actingAs($staff);

    Livewire::test(ClientLicenses::class, ['client' => $client])
        ->call('openLicenseModal')
        ->set('name', 'Elementor Pro')
        ->set('domainId', $ownDomain->id)
        ->call('saveLicense')
        ->assertHasNoErrors();

    Livewire::test(ClientLicenses::class, ['client' => $client])
        ->call('openLicenseModal')
        ->set('name', 'WP Rocket')
        ->set('domainId', $foreignDomain->id)
        ->call('saveLicense')
        ->assertHasErrors('domainId');

    expect($client->licenses()->count())->toBe(1);
});

test('staff cannot see or set license credentials', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $license = License::factory()->for($client)->create([
        'username' => 'cuenta@brevo.test',
        'password' => 'clave-brevo',
    ]);

    $this->actingAs($staff);

    $component = Livewire::test(ClientLicenses::class, ['client' => $client])
        ->assertDontSee('clave-brevo');

    expect($component->get('canSeeCredentials'))->toBeFalse();

    $component->call('revealPassword', $license->id)->assertForbidden();
});

test('an admin editing a license without retyping the password keeps the stored one', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create();
    $license = License::factory()->for($client)->create(['password' => 'clave-brevo']);

    $this->actingAs($admin);

    Livewire::test(ClientLicenses::class, ['client' => $client])
        ->call('openLicenseModal', $license->id)
        ->set('password', '')
        ->set('vendor', 'Otro proveedor')
        ->call('saveLicense')
        ->assertHasNoErrors();

    $license->refresh();

    expect($license->vendor)->toBe('Otro proveedor')
        ->and($license->password)->toBe('clave-brevo');
});

test('a license password is never stored in plain text', function () {
    $client = Client::factory()->client()->create();
    $license = License::factory()->for($client)->create(['password' => 'clave-brevo']);

    $raw = DB::table('licenses')->where('id', $license->id)->value('password');

    expect($raw)->not->toBe('clave-brevo')
        ->and($license->fresh()->password)->toBe('clave-brevo');
});

test('renewing a license arms its expiry reminder again', function () {
    $license = License::factory()->create(['expiry_notified_at' => now()->subDay()]);

    $license->update(['renewal_date' => now()->addYear()->toDateString()]);

    expect($license->refresh()->expiry_notified_at)->toBeNull();
});
