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

test('el proyecto toma la agencia de su cliente, sin asociarla aparte', function () {
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

    expect($project->fresh()->client->agency_id)->toBe($agency->id);
});

test('moving the status switch on the client detail applies the change without a save button', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->prospect()->create();

    $this->actingAs($admin);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->set('note', 'Llamada de seguimiento')
        ->call('addNote')
        ->assertHasNoErrors()
        ->set('status', ClientStatus::Ganado->value)
        ->assertHasNoErrors();

    $client->refresh();

    expect($client->status)->toBe(ClientStatus::Ganado)
        ->and($client->type)->toBe(ClientType::Client)
        ->and($client->notes)->toHaveCount(2);
});

test('the client detail opens on domains and shows each panel in its own tab, with the log always visible', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->assertSet('tab', 'dominios')
        ->assertSee('Bitácora')
        ->assertSee('Dominios y correo')
        ->assertSee('Licencias y suscripciones')
        ->assertDontSee('Proyectos')
        ->set('tab', 'trabajo')
        ->assertSee('Bitácora')
        ->assertSee('Proyectos')
        ->assertSee('Cotizaciones')
        ->assertSee('Campañas de ads')
        ->assertDontSee('Contratos')
        ->set('tab', 'cobros')
        ->assertSee('Bitácora')
        ->assertSee('Todo lo cobrado y por cobrar del cliente', escape: false)
        ->assertSee('Contratos')
        ->assertDontSee('Cotizaciones')
        ->set('tab', 'renovaciones')
        ->assertSee('servicios anuales que caducan', escape: false)
        ->assertDontSee('Contratos');
});

test('winning a prospect keeps the open tab when the ficha moves to the client url', function () {
    $admin = User::factory()->admin()->create();
    $prospect = Client::factory()->prospect()->create();

    $this->actingAs($admin);

    Livewire::test('pages::clients.show', ['client' => $prospect])
        ->set('routeName', 'prospects.show')
        ->set('tab', 'trabajo')
        ->set('status', ClientStatus::Ganado->value)
        ->assertRedirect(route('clients.show', ['client' => $prospect, 'seccion' => 'trabajo']));
});

test('a hand-typed section in the url falls back to the first tab', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    /**
     * Vía HTTP y no ->set(): cambiar a un tab inválido y de regreso al mismo
     * valor por omisión no deja rastro en el diff de Livewire, así que sus
     * componentes hijos (DomainsPanel, etc.) no se vuelven a pintar y la
     * aserción saldría en falso positivo.
     */
    $this->get(route('clients.show', ['client' => $client, 'seccion' => 'inventada']))
        ->assertSee('Dominios y correo');
});

test('the client detail lists the projects of that client only', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    Project::factory()->for($client)->create(['name' => 'Sitio propio']);
    Project::factory()->create(['name' => 'Proyecto ajeno']);

    $this->actingAs($staff);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->set('tab', 'trabajo')
        ->assertSee('Sitio propio')
        ->assertDontSee('Proyecto ajeno');
});

test('a client with no projects says so instead of looking broken', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->set('tab', 'trabajo')
        ->assertSee('No todos los clientes necesitan uno', escape: false);
});

test('the general data card shows agency, owner and currency, and says so when they are missing', function () {
    $admin = User::factory()->admin()->create();
    $agency = Agency::factory()->create(['name' => 'Agencia Norte']);
    $client = Client::factory()->client()->create([
        'agency_id' => $agency->id,
        'assigned_to_user_id' => $admin->id,
        'currency' => 'MXN',
    ]);
    $direct = Client::factory()->client()->create(['agency_id' => null, 'assigned_to_user_id' => null, 'source' => null]);

    $this->actingAs($admin);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->assertSee('Agencia Norte')
        ->assertSee($admin->name)
        ->assertSee('MXN');

    Livewire::test('pages::clients.show', ['client' => $direct])
        ->assertSee('Contacto directo')
        ->assertSee('Sin asignar')
        ->assertSee('Sin registrar');
});

test('the owner can be changed from the client detail and from the edit modal', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create(['assigned_to_user_id' => null]);

    $this->actingAs($admin);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->set('assignedTo', $staff->id)
        ->assertHasNoErrors();

    expect($client->refresh()->assigned_to_user_id)->toBe($staff->id);

    Livewire::test('pages::clients.index')
        ->call('openEditModal', $client->id)
        ->set('assigned_to_user_id', $admin->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($client->refresh()->assigned_to_user_id)->toBe($admin->id);
});

test('an owner must be an internal user', function () {
    $admin = User::factory()->admin()->create();
    $collaborator = User::factory()->collaborator()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($admin);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->set('assignedTo', $collaborator->id)
        ->assertHasErrors('assignedTo');

    expect($client->refresh()->assigned_to_user_id)->not->toBe($collaborator->id);
});

test('the log shows notes and status changes with their author, and the empty state until there is activity', function () {
    $admin = User::factory()->admin()->create(['name' => 'Laura Méndez']);
    $client = Client::factory()->client()->create();

    $this->actingAs($admin);

    $component = Livewire::test('pages::clients.show', ['client' => $client])
        ->assertSee('Sin actividad todavía.')
        ->set('note', 'Pidió factura')
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSee('Pidió factura')
        ->assertSee('Laura Méndez')
        ->assertDontSee('Sin actividad todavía.');

    $component->set('status', ClientStatus::Inactivo->value)
        ->assertSee('Cambio de estatus')
        ->assertSee('Estatus cambiado de');
});
