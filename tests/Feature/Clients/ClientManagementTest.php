<?php

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
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
