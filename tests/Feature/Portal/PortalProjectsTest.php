<?php

use App\Enums\UserRole;
use App\Models\Charge;
use App\Models\Client;
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

test('client can only see their own projects', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client($client)->create();

    $ownProject = Project::factory()->for($client)->create(['name' => 'Sitio propio']);
    Project::factory()->create(['name' => 'Proyecto ajeno']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.projects.index')
        ->assertSee('Sitio propio')
        ->assertDontSee('Proyecto ajeno');
});

test('client can view their own project detail with services and charges', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client($client)->create();

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
    $clientUser = User::factory()->client($client)->create();

    $otherProject = Project::factory()->create();

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.projects.show', ['project' => $otherProject])
        ->assertForbidden();
});

test('client user without a linked client record is forbidden from the portal', function () {
    $clientUser = User::factory()->create(['role' => UserRole::Client, 'client_id' => null]);

    $this->actingAs($clientUser);

    $this->get(route('portal.projects.index'))->assertForbidden();
});
