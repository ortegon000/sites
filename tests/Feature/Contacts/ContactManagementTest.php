<?php

use App\Models\Client;
use App\Models\Contact;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('only admin and staff reach the contacts pages', function (string $factoryState, bool $allowed) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user);

    $response = $this->get(route('contacts.index'));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    ['admin', true],
    ['staff', true],
    ['collaborator', false],
]);

test('capturing the same person on two companies reuses the contact instead of duplicating it', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff);

    foreach (['Tacos El Güero SA', 'Inmobiliaria Norte SA'] as $companyName) {
        Livewire::test('pages::clients.index')
            ->call('openCreateModal')
            ->set('name', $companyName)
            ->set('contact_name', 'Juan Pérez')
            ->set('email', 'juan@ejemplo.test')
            ->set('phone', '55 1234 5678')
            ->call('save')
            ->assertHasNoErrors();
    }

    expect(Contact::where('email', 'juan@ejemplo.test')->count())->toBe(1);

    $juan = Contact::where('email', 'juan@ejemplo.test')->firstOrFail();

    expect($juan->clients)->toHaveCount(2)
        ->and($juan->clients->pluck('name')->all())
        ->toEqualCanonicalizing(['Tacos El Güero SA', 'Inmobiliaria Norte SA']);
});

test('the client form loads and updates its primary contact', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $contact = Contact::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@ejemplo.test']);
    $client->contacts()->attach($contact, ['is_primary' => true]);

    $this->actingAs($staff);

    Livewire::test('pages::clients.index')
        ->call('openEditModal', $client->id)
        ->assertSet('contact_name', 'Ana Gómez')
        ->assertSet('email', 'ana@ejemplo.test')
        ->set('phone', '55 9999 0000')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->refresh()->phone)->toBe('55 9999 0000')
        ->and($client->contacts()->count())->toBe(1);
});

test('a company can hold several contacts and only one is primary', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    $component = Livewire::test('pages::clients.show', ['client' => $client])
        ->set('newContactName', 'Ana Gómez')
        ->set('newContactEmail', 'ana@ejemplo.test')
        ->call('addContact')
        ->set('newContactName', 'Luis Ruiz')
        ->set('newContactEmail', 'luis@ejemplo.test')
        ->set('newContactRole', 'Administración')
        ->call('addContact')
        ->assertHasNoErrors();

    expect($client->contacts()->count())->toBe(2)
        ->and($client->contacts()->wherePivot('is_primary', true)->count())->toBe(1)
        ->and($client->primaryContact()->name)->toBe('Ana Gómez');

    $luis = Contact::where('email', 'luis@ejemplo.test')->firstOrFail();

    $component->call('makeContactPrimary', $luis->id);

    $client->unsetRelation('contacts');

    expect($client->contacts()->wherePivot('is_primary', true)->count())->toBe(1)
        ->and($client->primaryContact()->name)->toBe('Luis Ruiz');
});

test('unlinking a contact from a company keeps the person and their other companies', function () {
    $staff = User::factory()->staff()->create();
    $tacos = Client::factory()->client()->create();
    $inmobiliaria = Client::factory()->client()->create();
    $contact = Contact::factory()->create();
    $contact->clients()->attach([$tacos->id, $inmobiliaria->id], ['is_primary' => true]);

    $this->actingAs($staff);

    Livewire::test('pages::clients.show', ['client' => $tacos])
        ->call('detachContact', $contact->id);

    expect(Contact::find($contact->id))->not->toBeNull()
        ->and($tacos->contacts()->count())->toBe(0)
        ->and($inmobiliaria->contacts()->count())->toBe(1);
});

test('the contact detail lists every company of that person', function () {
    $staff = User::factory()->staff()->create();
    $tacos = Client::factory()->client()->create(['name' => 'Tacos El Güero SA']);
    $inmobiliaria = Client::factory()->client()->create(['name' => 'Inmobiliaria Norte SA']);
    $ajena = Client::factory()->client()->create(['name' => 'Empresa Ajena SA']);

    $contact = Contact::factory()->create(['name' => 'Juan Pérez']);
    $contact->clients()->attach($tacos, ['is_primary' => true, 'role' => 'Director general']);
    $contact->clients()->attach($inmobiliaria, ['is_primary' => true]);

    Project::factory()->for($tacos)->create();

    $this->actingAs($staff);

    Livewire::test('pages::contacts.show', ['contact' => $contact])
        ->assertSee('Tacos El Güero SA')
        ->assertSee('Inmobiliaria Norte SA')
        ->assertSee('Director general')
        ->assertDontSee('Empresa Ajena SA');
});

test('two contacts cannot share an email', function () {
    $staff = User::factory()->staff()->create();
    Contact::factory()->create(['email' => 'juan@ejemplo.test']);

    $this->actingAs($staff);

    Livewire::test('pages::contacts.index')
        ->call('openCreateModal')
        ->set('name', 'Otro Juan')
        ->set('email', 'juan@ejemplo.test')
        ->call('save')
        ->assertHasErrors('email');
});

test('linking an existing person without repeating their phone does not erase it', function () {
    $staff = User::factory()->staff()->create();
    $contact = Contact::factory()->create([
        'name' => 'Juan Pérez',
        'email' => 'juan@ejemplo.test',
        'phone' => '55 1234 5678',
    ]);

    $this->actingAs($staff);

    Livewire::test('pages::clients.index')
        ->call('openCreateModal')
        ->set('name', 'Otra Empresa SA')
        ->set('contact_name', 'Juan Pérez')
        ->set('email', 'juan@ejemplo.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->refresh()->phone)->toBe('55 1234 5678')
        ->and($contact->clients()->count())->toBe(1);
});
