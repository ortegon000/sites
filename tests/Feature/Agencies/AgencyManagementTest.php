<?php

use App\Enums\AgencyStatus;
use App\Models\Agency;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('agencies.index'))->assertRedirect(route('login'));
});

test('admin can view the agencies list', function () {
    $admin = User::factory()->admin()->create();
    Agency::factory()->create(['name' => 'Northwind Digital']);

    $this->actingAs($admin);

    Livewire::test('pages::agencies.index')
        ->assertSee('Northwind Digital');
});

test('staff can create an agency', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff);

    Livewire::test('pages::agencies.index')
        ->set('name', 'Pixel Forge Studio')
        ->set('email', 'hola@pixelforge.test')
        ->set('status', AgencyStatus::Activa->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(Agency::where('name', 'Pixel Forge Studio')->first()?->email)->toBe('hola@pixelforge.test');
});

test('staff can edit an agency but cannot delete it', function () {
    $staff = User::factory()->staff()->create();
    $agency = Agency::factory()->create(['name' => 'Old Name']);

    $this->actingAs($staff);

    Livewire::test('pages::agencies.index')
        ->call('openEditModal', $agency->id)
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($agency->fresh()->name)->toBe('New Name')
        ->and($staff->can('delete', $agency))->toBeFalse();
});

test('admin can delete an agency', function () {
    $admin = User::factory()->admin()->create();
    $agency = Agency::factory()->create();

    $this->actingAs($admin);

    Livewire::test('pages::agencies.index')
        ->call('delete', $agency->id);

    expect(Agency::find($agency->id))->toBeNull();
});

test('collaborator cannot view the agencies list', function () {
    $collaborator = User::factory()->collaborator()->create();

    $this->actingAs($collaborator);

    $this->get(route('agencies.index'))->assertForbidden();
});

test('client role cannot view the agencies list', function () {
    $clientUser = User::factory()->client()->create();

    $this->actingAs($clientUser);

    $this->get(route('agencies.index'))->assertForbidden();
});

test('collaborator cannot access the agency policy', function () {
    $collaborator = User::factory()->collaborator()->create();

    expect($collaborator->can('viewAny', Agency::class))->toBeFalse();
});

test('el listado reporta lo cobrado y lo que falta por cobrar de cada agencia', function () {
    $admin = User::factory()->admin()->create();
    $agency = Agency::factory()->create(['name' => 'AgenciaEfe5']);

    $client = Client::factory()->client()->create(['agency_id' => $agency->id]);
    $service = Service::factory()->monthly()->for($client)->standalone()->create();
    $charge = Charge::factory()->for($service)->pending()->create(['amount' => '24000.00']);
    $charge->payments()->create(['amount' => '12914.00', 'paid_on' => today()->toDateString()]);
    $charge->syncStatusFromPayments();

    $this->actingAs($admin);

    Livewire::test('pages::agencies.index')
        ->assertSee('12,914.00')
        ->assertSee('11,086.00');
});
