<?php

use App\Enums\EmailProviderDriverType;
use App\Enums\EmailProviderStatus;
use App\Models\EmailProvider;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('email-providers.index'))->assertRedirect(route('login'));
});

test('staff cannot access email providers', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff);

    $this->get(route('email-providers.index'))->assertForbidden();
});

test('admin can view the email providers list', function () {
    $admin = User::factory()->admin()->create();
    EmailProvider::factory()->create(['name' => 'MXroute principal']);

    $this->actingAs($admin);

    Livewire::test('pages::email-providers.index')
        ->assertSee('MXroute principal');
});

test('admin can create an email provider', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::email-providers.index')
        ->set('name', 'Nuevo proveedor')
        ->set('driver', EmailProviderDriverType::NullDriver->value)
        ->set('status', EmailProviderStatus::Activo->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(EmailProvider::where('name', 'Nuevo proveedor')->exists())->toBeTrue();
});

test('admin can delete an email provider', function () {
    $admin = User::factory()->admin()->create();
    $provider = EmailProvider::factory()->create();

    $this->actingAs($admin);

    Livewire::test('pages::email-providers.index')
        ->call('delete', $provider->id);

    expect(EmailProvider::find($provider->id))->toBeNull();
});
