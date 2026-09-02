<?php

use App\Enums\UserRole;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('portal.projects.index'))->assertRedirect(route('login'));
});

test('admin, staff and collaborator cannot access the portal', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user);

    $this->get(route('portal.projects.index'))->assertForbidden();
})->with(['admin', 'staff', 'collaborator']);

function portalContactFor(Client ...$clients): Contact
{
    $contact = Contact::factory()->create();

    foreach ($clients as $client) {
        $contact->clients()->attach($client, ['is_primary' => true]);
    }

    return $contact;
}

test('client can only see their own projects', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    $ownProject = Project::factory()->for($client)->create(['name' => 'Sitio propio']);
    Project::factory()->create(['name' => 'Proyecto ajeno']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.projects.index')
        ->assertSee('Sitio propio')
        ->assertDontSee('Proyecto ajeno');
});

test('client can view their own project detail with services and charges', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    $project = Project::factory()->for($client)->create(['name' => 'Sitio propio']);
    $service = Service::factory()->for($project)->create(['name' => 'Hosting anual']);
    Charge::factory()->for($service)->pending()->create(['amount' => '1234.00']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.projects.show', ['project' => $project])
        ->assertSee('Sitio propio')
        ->assertSee('Hosting anual')
        ->assertSee('1234');
});

test('client cannot view another client\'s project detail', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    $otherProject = Project::factory()->create();

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.projects.show', ['project' => $otherProject])
        ->assertForbidden();
});

test('a contact who owns several companies sees all of them with one login', function () {
    $tacos = Client::factory()->client()->create(['name' => 'Tacos El Güero SA']);
    $inmobiliaria = Client::factory()->client()->create(['name' => 'Inmobiliaria Norte SA']);
    $ajena = Client::factory()->client()->create(['name' => 'Empresa Ajena SA']);

    $clientUser = User::factory()->client(portalContactFor($tacos, $inmobiliaria))->create();

    Project::factory()->for($tacos)->create(['name' => 'Sitio de tacos']);
    Project::factory()->for($inmobiliaria)->create(['name' => 'Sitio inmobiliario']);
    Project::factory()->for($ajena)->create(['name' => 'Proyecto ajeno']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.projects.index')
        ->assertSee('Sitio de tacos')
        ->assertSee('Sitio inmobiliario')
        ->assertSee('Tacos El Güero SA')
        ->assertSee('Inmobiliaria Norte SA')
        ->assertDontSee('Proyecto ajeno');
});

test('the company column is hidden when the contact only has one', function () {
    $client = Client::factory()->client()->create(['name' => 'Única Empresa SA']);
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    Project::factory()->for($client)->create(['name' => 'Su proyecto']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.projects.index')
        ->assertSee('Su proyecto')
        ->assertDontSee('Única Empresa SA');
});

test('client user without a linked contact is forbidden from the portal', function () {
    $clientUser = User::factory()->create(['role' => UserRole::Client, 'contact_id' => null]);

    $this->actingAs($clientUser);

    $this->get(route('portal.projects.index'))->assertForbidden();
});
