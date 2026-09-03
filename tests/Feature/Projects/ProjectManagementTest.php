<?php

use App\Enums\ChargeStatus;
use App\Enums\ProjectStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceStatus;
use App\Livewire\ChargesPanel;
use App\Livewire\ProjectsPanel;
use App\Livewire\ServicesPanel;
use App\Models\Agency;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});

test('admin can view the projects list', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create();
    Project::factory()->for($client)->create(['name' => 'Sitio web Acme']);

    $this->actingAs($admin);

    Livewire::test('pages::projects.index')
        ->assertSee('Sitio web Acme');
});

test('collaborator only sees projects assigned to them', function () {
    $collaborator = User::factory()->collaborator()->create();
    $client = Client::factory()->client()->create();

    $assigned = Project::factory()->for($client)->create(['name' => 'Proyecto asignado']);
    $assigned->users()->attach($collaborator);

    Project::factory()->for($client)->create(['name' => 'Proyecto ajeno']);

    $this->actingAs($collaborator);

    Livewire::test('pages::projects.index')
        ->assertSee('Proyecto asignado')
        ->assertDontSee('Proyecto ajeno');
});

test('client role cannot view the projects list', function () {
    $clientUser = User::factory()->client()->create();

    $this->actingAs($clientUser);

    $this->get(route('projects.index'))->assertForbidden();
});

test('staff can create a project', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test('pages::projects.index')
        ->set('name', 'Nuevo proyecto')
        ->set('client_id', $client->id)
        ->set('status', ProjectStatus::Activo->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(Project::where('name', 'Nuevo proyecto')->exists())->toBeTrue();
});

test('el proyecto nuevo queda bajo la agencia de su cliente', function () {
    $staff = User::factory()->staff()->create();
    $agency = Agency::factory()->create();
    $client = Client::factory()->client()->create(['agency_id' => $agency->id]);

    $this->actingAs($staff);

    Livewire::test('pages::projects.index')
        ->set('name', 'Nuevo proyecto')
        ->set('client_id', $client->id)
        ->set('status', ProjectStatus::Activo->value)
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::where('name', 'Nuevo proyecto')->firstOrFail();

    expect($project->client->agency_id)->toBe($agency->id);
});

test('collaborator cannot access the project policy for creating or updating', function () {
    $collaborator = User::factory()->collaborator()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();

    expect($collaborator->can('create', Project::class))->toBeFalse()
        ->and($collaborator->can('update', $project))->toBeFalse();
});

test('collaborator does not see charge amounts or the cobros section on an assigned project', function () {
    $collaborator = User::factory()->collaborator()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();
    $project->users()->attach($collaborator);

    $service = Service::factory()->monthly()->for($project)->create();
    Charge::factory()->for($service)->pending()->create();

    $this->actingAs($collaborator);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertDontSee('Cobros')
        ->assertDontSee('Agencias colaboradoras')
        ->assertDontSee($service->amount);
});

test('collaborator can view an assigned project but not one they are not assigned to', function () {
    $collaborator = User::factory()->collaborator()->create();
    $client = Client::factory()->client()->create();

    $assigned = Project::factory()->for($client)->create();
    $assigned->users()->attach($collaborator);

    $notAssigned = Project::factory()->for($client)->create();

    $this->actingAs($collaborator);

    Livewire::test('pages::projects.show', ['project' => $assigned])->assertOk();

    expect($collaborator->can('view', $notAssigned))->toBeFalse();
});

test('staff can add a service to a project from its services panel', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();

    $this->actingAs($staff);

    Livewire::test(ServicesPanel::class, ['client' => $client, 'project' => $project])
        ->set('serviceName', 'Hosting anual')
        ->set('billingFrequency', ServiceBillingFrequency::Annual->value)
        ->set('amount', '1200.00')
        ->set('currency', 'MXN')
        ->set('serviceStatus', ServiceStatus::Activo->value)
        ->set('startsOn', now()->toDateString())
        ->call('saveService')
        ->assertHasNoErrors();

    $project->refresh();

    expect($project->services)->toHaveCount(1)
        ->and($project->services->first()->charges)->toHaveCount(1);
});

test('staff can mark a charge as paid from the project charges panel', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();
    $service = Service::factory()->monthly()->for($project)->create();
    $charge = Charge::factory()->for($service)->pending()->create();

    $this->actingAs($staff);

    Livewire::test(ChargesPanel::class, ['client' => $client, 'project' => $project])
        ->call('markChargeAsPaid', $charge->id);

    expect($charge->fresh()->status)->toBe(ChargeStatus::Pagado)
        ->and($charge->fresh()->paid_at)->not->toBeNull();
});

test('admin can assign and unassign a user from a project team', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();

    $this->actingAs($admin);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->set('userIdToAssign', $staff->id)
        ->call('assignUser')
        ->assertHasNoErrors();

    expect($project->users()->whereKey($staff->id)->exists())->toBeTrue();

    Livewire::test('pages::projects.show', ['project' => $project])
        ->call('unassignUser', $staff->id);

    expect($project->users()->whereKey($staff->id)->exists())->toBeFalse();
});

test('el listado de proyectos se puede filtrar por agencia', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $agency = Agency::factory()->create();

    $agencyClient = Client::factory()->client()->create(['agency_id' => $agency->id]);
    Project::factory()->for($agencyClient)->create(['name' => 'Sitio de AgenciaEfe5']);

    Project::factory()->for($client)->create(['name' => 'Sitio propio']);

    $this->actingAs($staff);

    Livewire::test('pages::projects.index')
        ->set('agencyFilter', $agency->id)
        ->assertSee('Sitio de AgenciaEfe5')
        ->assertDontSee('Sitio propio');
});

test('la tarjeta de la ficha del cliente da de alta el proyecto sin salir de ahí', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(ProjectsPanel::class, ['client' => $client])
        ->call('openCreateModal')
        ->assertSet('client_id', $client->id)
        ->set('name', 'Sitio desde la ficha')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Sitio desde la ficha');

    expect(Project::where('name', 'Sitio desde la ficha')->first()?->client_id)->toBe($client->id);
});

test('el cliente de la tarjeta manda sobre el que llegue del navegador', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $otherClient = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(ProjectsPanel::class, ['client' => $client])
        ->call('openCreateModal')
        ->set('name', 'Proyecto ajeno')
        ->set('client_id', $otherClient->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Project::where('name', 'Proyecto ajeno')->first()?->client_id)->toBe($client->id);
});

test('no se puede editar el proyecto de otro cliente desde esta tarjeta', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $foreign = Project::factory()->for(Client::factory()->client())->create();

    $this->actingAs($staff);

    expect(fn () => Livewire::test(ProjectsPanel::class, ['client' => $client])->call('openEditModal', $foreign->id))
        ->toThrow(ModelNotFoundException::class);
});
