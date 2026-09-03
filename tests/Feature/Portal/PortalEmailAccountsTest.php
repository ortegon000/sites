<?php

use App\Enums\DomainEmailManagement;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

function portalEmailContactFor(Client $client): Contact
{
    $contact = Contact::factory()->create();
    $contact->clients()->attach($client, ['is_primary' => true]);

    return $contact;
}

function portalDomainFor(Client $client): Domain
{
    $project = Project::factory()->for($client)->create();

    return Domain::factory()->for($client)->withManagedEmail()->create();
}

test('guests are redirected to the login page', function () {
    $this->get(route('portal.email-accounts.index'))->assertRedirect(route('login'));
});

test('admin, staff and collaborator cannot access the portal email accounts page', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user);

    $this->get(route('portal.email-accounts.index'))->assertForbidden();
})->with(['admin', 'staff', 'collaborator']);

test('client can only see the mailboxes on their own domains', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalEmailContactFor($client))->create();

    $ownDomain = portalDomainFor($client);
    EmailAccount::factory()->for($ownDomain)->create(['email_address' => 'propio@cliente.test']);

    $foreignDomain = portalDomainFor(Client::factory()->client()->create());
    EmailAccount::factory()->for($foreignDomain)->create(['email_address' => 'ajeno@otro.test']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.email-accounts.index')
        ->assertSee($ownDomain->name)
        ->assertSee('propio@cliente.test')
        ->assertDontSee('ajeno@otro.test');
});

test('a domain whose email we do not manage is not listed in the portal', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalEmailContactFor($client))->create();

    $domain = Domain::factory()->for($client)->create([
        'email_management' => DomainEmailManagement::NotManaged,
        'email_notes' => 'Google Workspace del cliente',
    ]);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.email-accounts.index')
        ->assertDontSee($domain->name);
});

test('client sees connection settings for their email account', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalEmailContactFor($client))->create();

    EmailAccount::factory()->for(portalDomainFor($client))->create();

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.email-accounts.index')
        ->assertSee('imap.simulado.test')
        ->assertSee('smtp.simulado.test');
});

test('a stored password is hidden until the client asks to see it', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalEmailContactFor($client))->create();

    $emailAccount = EmailAccount::factory()->for(portalDomainFor($client))->create([
        'password' => 'secreto-del-buzon',
    ]);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.email-accounts.index')
        ->assertDontSee('secreto-del-buzon')
        ->call('revealPassword', $emailAccount->id)
        ->assertSee('secreto-del-buzon')
        ->call('hidePassword', $emailAccount->id)
        ->assertDontSee('secreto-del-buzon');
});

test('client user without a linked contact is forbidden', function () {
    $clientUser = User::factory()->create(['role' => UserRole::Client, 'contact_id' => null]);

    $this->actingAs($clientUser);

    $this->get(route('portal.email-accounts.index'))->assertForbidden();
});
