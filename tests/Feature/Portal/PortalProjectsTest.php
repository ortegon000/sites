<?php

use App\Models\Charge;
use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

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
        ->assertSee('1,234.00');
});

test('client cannot view another client\'s project detail', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    $otherProject = Project::factory()->create();

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.projects.show', ['project' => $otherProject])
        ->assertForbidden();
});
