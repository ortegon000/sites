<?php

use App\Enums\ProjectType;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('choosing a project type prefills its suggested services and the email flag', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff);

    $component = Livewire::test('pages::projects.index')
        ->call('openCreateModal')
        ->set('type', ProjectType::Web->value);

    expect($component->get('includes_email'))->toBeTrue()
        ->and($component->get('templateServices'))->toHaveCount(5)
        ->and(collect($component->get('templateServices'))->pluck('category')->all())
        ->toBe(['website', 'hosting', 'ssl', 'email', 'domain']);

    $component->set('type', ProjectType::Ads->value);

    expect($component->get('includes_email'))->toBeFalse()
        ->and($component->get('templateServices'))->toHaveCount(1);
});

test('creating a web project also creates the services that were left ticked', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    $component = Livewire::test('pages::projects.index')
        ->call('openCreateModal')
        ->set('type', ProjectType::Web->value)
        ->set('name', 'Sitio Acme')
        ->set('client_id', $client->id);

    $services = $component->get('templateServices');
    $services[0]['amount'] = '25000';
    $services[1]['amount'] = '1800';
    $services[2]['enabled'] = false;
    $services[3]['enabled'] = false;
    $services[4]['amount'] = '450';

    $component->set('templateServices', $services)
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::where('name', 'Sitio Acme')->firstOrFail();

    expect($project->type)->toBe(ProjectType::Web)
        ->and($project->includes_email)->toBeTrue()
        ->and($project->services()->count())->toBe(3)
        ->and($project->services()->pluck('category')->map->value->all())->toBe(['website', 'hosting', 'domain']);

    $website = $project->services()->where('category', ServiceCategory::Website)->firstOrFail();
    $hosting = $project->services()->where('category', ServiceCategory::Hosting)->firstOrFail();

    expect($website->billing_frequency)->toBe(ServiceBillingFrequency::OneTime)
        ->and($hosting->billing_frequency)->toBe(ServiceBillingFrequency::Annual)
        ->and($hosting->next_charge_date)->not->toBeNull()
        ->and($hosting->charges()->count())->toBe(1);
});

test('an enabled template service requires an amount', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test('pages::projects.index')
        ->call('openCreateModal')
        ->set('type', ProjectType::Maintenance->value)
        ->set('name', 'Mantenimiento Acme')
        ->set('client_id', $client->id)
        ->call('save')
        ->assertHasErrors('templateServices.0.amount');

    expect(Project::where('name', 'Mantenimiento Acme')->exists())->toBeFalse();
});

test('editing a project leaves its services alone', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create(['type' => ProjectType::Other]);

    $this->actingAs($staff);

    Livewire::test('pages::projects.index')
        ->call('openEditModal', $project->id)
        ->set('type', ProjectType::Web->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($project->refresh()->type)->toBe(ProjectType::Web)
        ->and($project->services()->count())->toBe(0);
});

test('a service can be tied to a domain of its project', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();
    $domain = Domain::factory()->for($client)->for($project)->create();

    $this->actingAs($staff);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->call('openServiceModal')
        ->set('serviceName', 'Renovación de dominio')
        ->set('serviceCategory', ServiceCategory::Domain->value)
        ->set('serviceDomainId', $domain->id)
        ->set('billingFrequency', ServiceBillingFrequency::Annual->value)
        ->set('amount', '450')
        ->set('startsOn', now()->toDateString())
        ->call('saveService')
        ->assertHasNoErrors();

    $service = $project->services()->firstOrFail();

    expect($service->domain_id)->toBe($domain->id)
        ->and($service->category)->toBe(ServiceCategory::Domain);
});

test('a service cannot be tied to a domain of another project', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $foreignDomain = Domain::factory()->create();

    $this->actingAs($staff);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->call('openServiceModal')
        ->set('serviceName', 'Renovación de dominio')
        ->set('serviceCategory', ServiceCategory::Domain->value)
        ->set('serviceDomainId', $foreignDomain->id)
        ->set('billingFrequency', ServiceBillingFrequency::Annual->value)
        ->set('amount', '450')
        ->set('startsOn', now()->toDateString())
        ->call('saveService')
        ->assertHasErrors('serviceDomainId');

    expect($project->services()->count())->toBe(0);
});
