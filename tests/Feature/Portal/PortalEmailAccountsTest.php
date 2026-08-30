<?php

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\EmailAccount;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('portal.email-accounts.index'))->assertRedirect(route('login'));
});

test('admin, staff and collaborator cannot access the portal email accounts page', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user);

    $this->get(route('portal.email-accounts.index'))->assertForbidden();
})->with(['admin', 'staff', 'collaborator']);

test('client can only see their own email accounts', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client($client)->create();

    EmailAccount::factory()->for($client)->create(['email_address' => 'propio@cliente.test']);
    EmailAccount::factory()->create(['email_address' => 'ajeno@otro.test']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.email-accounts.index')
        ->assertSee('propio@cliente.test')
        ->assertDontSee('ajeno@otro.test');
});

test('client sees connection settings for their email account', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client($client)->create();

    EmailAccount::factory()->for($client)->create();

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.email-accounts.index')
        ->assertSee('imap.simulado.test')
        ->assertSee('smtp.simulado.test');
});

test('client user without a linked client record is forbidden', function () {
    $clientUser = User::factory()->create(['role' => UserRole::Client, 'client_id' => null]);

    $this->actingAs($clientUser);

    $this->get(route('portal.email-accounts.index'))->assertForbidden();
});
