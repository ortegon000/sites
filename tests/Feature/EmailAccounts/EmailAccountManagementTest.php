<?php

use App\Enums\EmailAccountStatus;
use App\Models\Client;
use App\Models\EmailAccount;
use App\Models\EmailProvider;
use App\Models\User;
use Livewire\Livewire;

test('staff can provision an email account for a client', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $provider = EmailProvider::factory()->create();

    $this->actingAs($staff);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->set('emailProviderIdToAssign', $provider->id)
        ->set('newEmailAddress', 'nueva@cliente.test')
        ->set('newEmailPassword', 'password123')
        ->call('provisionEmailAccount')
        ->assertHasNoErrors();

    expect($client->emailAccounts()->where('email_address', 'nueva@cliente.test')->exists())->toBeTrue();
});

test('staff can delete an email account', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $emailAccount = EmailAccount::factory()->for($client)->create();

    $this->actingAs($staff);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->call('deleteEmailAccount', $emailAccount->id);

    expect(EmailAccount::find($emailAccount->id))->toBeNull();
});

test('staff can change an email account password', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $emailAccount = EmailAccount::factory()->for($client)->create(['status' => EmailAccountStatus::Activa]);

    $this->actingAs($staff);

    Livewire::test('pages::clients.show', ['client' => $client])
        ->call('openPasswordModal', $emailAccount->id)
        ->set('newPassword', 'nueva-password')
        ->call('changePassword')
        ->assertHasNoErrors();

    expect($emailAccount->refresh()->status)->toBe(EmailAccountStatus::Activa);
});

test('collaborator cannot manage email accounts on a client', function () {
    $collaborator = User::factory()->collaborator()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($collaborator);

    $this->get(route('clients.show', $client))->assertForbidden();
});
