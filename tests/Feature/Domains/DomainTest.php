<?php

use App\Enums\DomainEmailManagement;
use App\Enums\ProjectType;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Project;

test('el dominio es del cliente y sobrevive a que se borre un proyecto suyo', function () {
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();
    $domain = Domain::factory()->for($client)->create();

    $project->delete();
    $project->forceDelete();

    expect($domain->fresh())->not->toBeNull()
        ->and($domain->fresh()->client_id)->toBe($client->id);
});

test('the same domain name can be registered for two different clients', function () {
    Domain::factory()->create(['name' => 'gmail.com']);

    $second = Domain::factory()->create(['name' => 'gmail.com']);

    expect($second->exists)->toBeTrue()
        ->and(Domain::where('name', 'gmail.com')->count())->toBe(2);
});

test('managing email depends on the domain alone', function () {
    $client = Client::factory()->client()->create();

    $managed = Domain::factory()->for($client)->withManagedEmail()->create();
    $another = Domain::factory()->for($client)->withManagedEmail()->create();

    expect($managed->managesEmail())->toBeTrue()
        ->and($another->managesEmail())->toBeTrue();
});

test('a domain that is not set to managed email never manages email', function () {
    $client = Client::factory()->client()->create();

    $domain = Domain::factory()->for($client)->create([
        'email_management' => DomainEmailManagement::NotManaged,
        'email_notes' => 'Google Workspace del cliente',
    ]);

    expect($domain->managesEmail())->toBeFalse();
});

test('project types carry the services they usually bill', function () {
    expect(ProjectType::Other->serviceTemplate())->toBe([])
        ->and(ProjectType::Web->serviceTemplate())->toHaveCount(5);
});
