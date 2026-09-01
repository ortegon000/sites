<?php

use App\Enums\DomainEmailManagement;
use App\Enums\ProjectType;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Project;

test('a domain belongs to the client and survives losing its project', function () {
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();
    $domain = Domain::factory()->for($client)->for($project)->create();

    $project->delete();
    $project->forceDelete();

    expect($domain->fresh()->project_id)->toBeNull()
        ->and($domain->fresh()->client_id)->toBe($client->id);
});

test('the same domain name can be registered for two different clients', function () {
    Domain::factory()->create(['name' => 'gmail.com']);

    $second = Domain::factory()->create(['name' => 'gmail.com']);

    expect($second->exists)->toBeTrue()
        ->and(Domain::where('name', 'gmail.com')->count())->toBe(2);
});

test('email can only be managed on a domain whose project includes email', function () {
    $client = Client::factory()->client()->create();
    $webProject = Project::factory()->for($client)->create([
        'type' => ProjectType::Web,
        'includes_email' => true,
    ]);
    $adsProject = Project::factory()->for($client)->create([
        'type' => ProjectType::Ads,
        'includes_email' => false,
    ]);

    $withEmail = Domain::factory()->for($client)->for($webProject)->withManagedEmail()->create();
    $withoutEmail = Domain::factory()->for($client)->for($adsProject)->withManagedEmail()->create();
    $orphan = Domain::factory()->for($client)->withManagedEmail()->create();

    expect($withEmail->canManageEmail())->toBeTrue()
        ->and($withEmail->managesEmail())->toBeTrue()
        ->and($withoutEmail->canManageEmail())->toBeFalse()
        ->and($withoutEmail->managesEmail())->toBeFalse()
        ->and($orphan->canManageEmail())->toBeFalse()
        ->and($orphan->managesEmail())->toBeFalse();
});

test('a domain that is not set to managed email never manages email', function () {
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create([
        'type' => ProjectType::Web,
        'includes_email' => true,
    ]);

    $domain = Domain::factory()->for($client)->for($project)->create([
        'email_management' => DomainEmailManagement::NotManaged,
        'email_notes' => 'Google Workspace del cliente',
    ]);

    expect($domain->canManageEmail())->toBeTrue()
        ->and($domain->managesEmail())->toBeFalse();
});

test('project types seed the includes_email flag', function () {
    expect(ProjectType::Web->includesEmailByDefault())->toBeTrue()
        ->and(ProjectType::Email->includesEmailByDefault())->toBeTrue()
        ->and(ProjectType::Ads->includesEmailByDefault())->toBeFalse()
        ->and(ProjectType::Maintenance->includesEmailByDefault())->toBeFalse()
        ->and(ProjectType::Other->serviceTemplate())->toBe([])
        ->and(ProjectType::Web->serviceTemplate())->toHaveCount(5);
});
