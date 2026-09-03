<?php

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('portal.services.index'))->assertRedirect(route('login'));
});

test('admin, staff and collaborator cannot access the portal', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user);

    $this->get(route('portal.services.index'))->assertForbidden();
})->with(['admin', 'staff', 'collaborator']);

test('client user without a linked contact is forbidden from the portal', function () {
    $clientUser = User::factory()->create(['role' => UserRole::Client, 'contact_id' => null]);

    $this->actingAs($clientUser);

    $this->get(route('portal.services.index'))->assertForbidden();
});

test('el portal muestra las líneas del cliente tengan proyecto o no', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    $project = Project::factory()->for($client)->create(['name' => 'Sitio institucional']);
    Service::factory()->for($client)->for($project)->create(['name' => 'Sitio web']);
    Service::factory()->for($client)->standalone()->create(['name' => 'Hosting anual']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.services.index')
        ->assertSee('Hosting anual')
        ->assertSee('Sitio web')
        ->assertSee('Sitio institucional');
});

test('client can only see the services of their own companies', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    Service::factory()->for($client)->standalone()->create(['name' => 'Hosting propio']);
    Service::factory()->standalone()->create(['name' => 'Hosting ajeno']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.services.index')
        ->assertSee('Hosting propio')
        ->assertDontSee('Hosting ajeno');
});

test('a contact who owns several companies sees all of them with one login', function () {
    $tacos = Client::factory()->client()->create(['name' => 'Tacos El Güero SA']);
    $inmobiliaria = Client::factory()->client()->create(['name' => 'Inmobiliaria Norte SA']);
    $ajena = Client::factory()->client()->create(['name' => 'Empresa Ajena SA']);

    $clientUser = User::factory()->client(portalContactFor($tacos, $inmobiliaria))->create();

    Service::factory()->for($tacos)->standalone()->create(['name' => 'Hosting de tacos']);
    Service::factory()->for($inmobiliaria)->standalone()->create(['name' => 'Hosting inmobiliario']);
    Service::factory()->for($ajena)->standalone()->create(['name' => 'Hosting ajeno']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.services.index')
        ->assertSee('Hosting de tacos')
        ->assertSee('Hosting inmobiliario')
        ->assertSee('Tacos El Güero SA')
        ->assertSee('Inmobiliaria Norte SA')
        ->assertDontSee('Hosting ajeno');
});

test('the company column is hidden when the contact only has one', function () {
    $client = Client::factory()->client()->create(['name' => 'Única Empresa SA']);
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    Service::factory()->for($client)->standalone()->create(['name' => 'Su hosting']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.services.index')
        ->assertSee('Su hosting')
        ->assertDontSee('Única Empresa SA');
});
