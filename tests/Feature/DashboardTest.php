<?php

use App\Enums\ChargeStatus;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\ProjectStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('client users are redirected to the portal instead of the dashboard', function () {
    $clientUser = User::factory()->client()->create();
    $this->actingAs($clientUser);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('portal.projects.index'));
});

test('admin sees financial KPIs, upcoming charges and recent activity', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();
    $service = Service::factory()->for($project)->create();

    Charge::factory()->for($service)->create([
        'status' => ChargeStatus::Pendiente,
        'due_date' => now()->addDays(3)->toDateString(),
    ]);
    Charge::factory()->for($service)->overdue()->create();
    ClientNote::factory()->for($client)->create(['body' => 'Nota reciente de prueba']);

    $this->actingAs($admin);

    Livewire::test('pages::dashboard.index')
        ->assertSee(__('Cobros pendientes'))
        ->assertSee(__('Cobros vencidos'))
        ->assertSee('Nota reciente de prueba');
});

test('staff sees the same dashboard as admin', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff);

    Livewire::test('pages::dashboard.index')
        ->assertSee(__('Cobros pendientes'))
        ->assertSee(__('Prospectos abiertos'));
});

test('collaborator does not see financial data, only their assigned projects', function () {
    $collaborator = User::factory()->collaborator()->create();
    $client = Client::factory()->client()->create();
    $assignedProject = Project::factory()->for($client)->create(['name' => 'Proyecto asignado']);
    $otherProject = Project::factory()->for($client)->create(['name' => 'Proyecto ajeno']);
    $assignedProject->users()->attach($collaborator);

    $this->actingAs($collaborator);

    $response = $this->get(route('dashboard'));

    $response->assertOk()
        ->assertDontSee(__('Cobros pendientes'))
        ->assertDontSee(__('Prospectos abiertos'))
        ->assertSee('Proyecto asignado')
        ->assertDontSee('Proyecto ajeno');
});

test('open prospects count only includes prospects in an open pipeline status', function () {
    $admin = User::factory()->admin()->create();

    Client::factory()->prospect()->create(['status' => ClientStatus::Nuevo]);
    Client::factory()->prospect()->create(['status' => ClientStatus::Ganado]);
    Client::factory()->create(['type' => ClientType::Client, 'status' => ClientStatus::Activo]);

    $this->actingAs($admin);

    $count = Livewire::test('pages::dashboard.index')->instance()->openProspectsCount();

    expect($count)->toBe(1);
});

test('el dashboard del colaborador lista todos sus proyectos asignados, no solo los activos', function () {
    $collaborator = User::factory()->collaborator()->create();
    $client = Client::factory()->client()->create();

    $active = Project::factory()->for($client)->create(['name' => 'Proyecto en curso', 'status' => ProjectStatus::Activo]);
    $finished = Project::factory()->for($client)->create(['name' => 'Proyecto terminado', 'status' => ProjectStatus::Completado]);

    $active->users()->attach($collaborator);
    $finished->users()->attach($collaborator);

    $this->actingAs($collaborator);

    /** Es su única entrada al sistema: sin menú de proyectos, lo que no salga aquí no existe para él. */
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Proyecto en curso')
        ->assertSee('Proyecto terminado');
});

test('el dashboard lista el cobro de una línea suelta, que no tiene proyecto', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create(['name' => 'Cliente Suelto']);
    $service = Service::factory()->standalone()->for($client)->create(['name' => 'Renovación anual']);

    Charge::factory()->for($service)->create([
        'status' => ChargeStatus::Pendiente,
        'due_date' => now()->addDays(2)->toDateString(),
    ]);

    $this->actingAs($admin);

    /** Antes reventaba aquí: la tabla enlazaba al proyecto, que ya puede no existir. */
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Cliente Suelto')
        ->assertSee('Renovación anual');
});
