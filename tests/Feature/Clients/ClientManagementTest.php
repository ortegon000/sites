<?php

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('clients.index'))->assertRedirect(route('login'));
});

test('admin can view the clients list', function () {
    $admin = User::factory()->admin()->create();
    Client::factory()->client()->create(['name' => 'Acme Corp']);

    $this->actingAs($admin);

    Livewire::test('pages::clients.index')
        ->assertSee('Acme Corp');
});

test('staff can create a client', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff);

    Livewire::test('pages::clients.index')
        ->set('name', 'New Client')
        ->set('email', 'new@example.com')
        ->set('currency', 'MXN')
        ->set('status', ClientStatus::Activo->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(Client::where('name', 'New Client')->exists())->toBeTrue();
});

test('collaborator cannot view the clients list', function () {
    $collaborator = User::factory()->collaborator()->create();

    $this->actingAs($collaborator);

    $this->get(route('clients.index'))->assertForbidden();
});

test('client role cannot view the clients list', function () {
    $clientUser = User::factory()->client()->create();

    $this->actingAs($clientUser);

    $this->get(route('clients.index'))->assertForbidden();
});

test('collaborator cannot access the client policy', function () {
    $collaborator = User::factory()->collaborator()->create();

    expect($collaborator->can('viewAny', Client::class))->toBeFalse();
});

test('changing a prospect status to ganado from the list edit modal converts it to a client', function () {
    $admin = User::factory()->admin()->create();
    $prospect = Client::factory()->prospect()->create();

    $this->actingAs($admin);

    Livewire::test('pages::clients.index')
        ->call('openEditModal', $prospect->id)
        ->set('status', ClientStatus::Ganado->value)
        ->call('save')
        ->assertHasNoErrors();

    $prospect->refresh();

    expect($prospect->status)->toBe(ClientStatus::Ganado)
        ->and($prospect->type)->toBe(ClientType::Client)
        ->and($prospect->notes)->toHaveCount(1);
});

test('visiting a prospect via the client url redirects to the prospect url', function () {
    $admin = User::factory()->admin()->create();
    $prospect = Client::factory()->prospect()->create();

    $this->actingAs($admin)
        ->get(route('clients.show', $prospect))
        ->assertRedirect(route('prospects.show', $prospect));
});

test('visiting a client via the prospect url redirects to the client url', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($admin)
        ->get(route('prospects.show', $client))
        ->assertRedirect(route('clients.show', $client));
});

test('selecting an agency prefills empty contact fields with the agency contact', function () {
    $staff = User::factory()->staff()->create();
    $agency = Agency::factory()->create([
        'contact_name' => 'Ana Gómez',
        'email' => 'ana@agencia.test',
        'phone' => '555-0001',
    ]);

    $this->actingAs($staff);

    Livewire::test('pages::clients.index')
        ->call('openCreateModal')
        ->set('agency_id', $agency->id)
        ->assertSet('contact_name', 'Ana Gómez')
        ->assertSet('email', 'ana@agencia.test')
        ->assertSet('phone', '555-0001');
});

test('selecting an agency does not overwrite a contact already captured', function () {
    $staff = User::factory()->staff()->create();
    $agency = Agency::factory()->create(['contact_name' => 'Ana Gómez']);

    $this->actingAs($staff);

    Livewire::test('pages::clients.index')
        ->call('openCreateModal')
        ->set('contact_name', 'Contacto Directo')
        ->set('agency_id', $agency->id)
        ->assertSet('contact_name', 'Contacto Directo');
});

test('assigning an agency to a client links all of its existing projects to that agency', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();
    $agency = Agency::factory()->create();

    $this->actingAs($staff);

    Livewire::test('pages::clients.index')
        ->call('openEditModal', $client->id)
        ->set('agency_id', $agency->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($project->fresh()->agencies)->toHaveCount(1)
        ->and($project->fresh()->agencies->first()->id)->toBe($agency->id)
        ->and($project->fresh()->agencies->first()->pivot->billing_direction)->toBeNull();
});

test('admin can add a note and change a prospect status to ganado, converting it to a client', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->prospect()->create();

    $this->actingAs($admin);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->set('note', 'Llamada de seguimiento')
        ->call('addNote')
        ->assertHasNoErrors()
        ->set('status', ClientStatus::Ganado->value)
        ->call('changeStatus')
        ->assertHasNoErrors();

    $client->refresh();

    expect($client->status)->toBe(ClientStatus::Ganado)
        ->and($client->type)->toBe(ClientType::Client)
        ->and($client->notes)->toHaveCount(2);
});

test('the client detail lists the projects of that client only', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    Project::factory()->for($client)->create(['name' => 'Sitio propio']);
    Project::factory()->create(['name' => 'Proyecto ajeno']);

    $this->actingAs($staff);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->assertSee('Sitio propio')
        ->assertDontSee('Proyecto ajeno');
});

test('a client with no projects says so instead of looking broken', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->assertSee('No todos los clientes necesitan uno', escape: false);
});
