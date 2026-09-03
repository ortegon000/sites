<?php

use App\Enums\DomainCredentialKind;
use App\Livewire\DomainsPanel;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\DomainCredential;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('admin can register a site access on a domain', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create();
    $domain = Domain::factory()->for($client)->create();

    $this->actingAs($admin);

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('openCredentialModal', $domain->id)
        ->set('credentialKind', DomainCredentialKind::Database->value)
        ->set('credentialLabel', 'acme_wp')
        ->set('credentialUsername', 'acme_admin')
        ->set('credentialPassword', 'clave-de-la-base')
        ->call('saveCredential')
        ->assertHasNoErrors();

    $credential = $domain->credentials()->firstOrFail();

    expect($credential->kind)->toBe(DomainCredentialKind::Database)
        ->and($credential->username)->toBe('acme_admin')
        ->and($credential->password)->toBe('clave-de-la-base');
});

test('a site access password is never stored in plain text', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create();
    $domain = Domain::factory()->for($client)->create();

    $this->actingAs($admin);

    $credential = DomainCredential::factory()->for($domain)->create(['password' => 'clave-cpanel']);

    $raw = DB::table('domain_credentials')->where('id', $credential->id)->value('password');

    expect($raw)->not->toBe('clave-cpanel')
        ->and($credential->fresh()->password)->toBe('clave-cpanel');
});

test('the password is hidden until it is asked for', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create();
    $domain = Domain::factory()->for($client)->create();
    $credential = DomainCredential::factory()->for($domain)->create(['password' => 'clave-cpanel']);

    $this->actingAs($admin);

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->assertDontSee('clave-cpanel')
        ->call('revealCredential', $credential->id)
        ->assertSee('clave-cpanel')
        ->call('hideCredential', $credential->id)
        ->assertDontSee('clave-cpanel');
});

test('staff manage domains but never see site accesses', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $domain = Domain::factory()->for($client)->create();
    $credential = DomainCredential::factory()->for($domain)->create([
        'password' => 'clave-cpanel',
        'username' => 'acme_admin',
    ]);

    $this->actingAs($staff);

    $component = Livewire::test(DomainsPanel::class, ['client' => $client])
        ->assertSee($domain->name)
        ->assertDontSee('acme_admin')
        ->assertDontSee('clave-cpanel');

    expect($component->get('canSeeCredentials'))->toBeFalse();

    $component->call('revealCredential', $credential->id)->assertForbidden();
});

test('a site access of another client cannot be reached', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create();
    $foreignCredential = DomainCredential::factory()->create();

    $this->actingAs($admin);

    expect(fn () => Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('revealCredential', $foreignCredential->id))
        ->toThrow(ModelNotFoundException::class);
});

test('site accesses never reach the client portal', function () {
    $client = Client::factory()->client()->create();
    $contact = Contact::factory()->create();
    $contact->clients()->attach($client, ['is_primary' => true]);
    $clientUser = User::factory()->client($contact)->create();

    $project = Project::factory()->for($client)->create();
    $domain = Domain::factory()->for($client)->withManagedEmail()->create();
    DomainCredential::factory()->for($domain)->create([
        'username' => 'acme_admin',
        'password' => 'clave-cpanel',
    ]);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.email-accounts.index')
        ->assertDontSee('acme_admin')
        ->assertDontSee('clave-cpanel');
});
