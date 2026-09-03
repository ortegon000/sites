<?php

use App\Models\Charge;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('portal.charges.index'))->assertRedirect(route('login'));
});

test('staff cannot access the client charges page', function () {
    $this->actingAs(User::factory()->staff()->create());

    $this->get(route('portal.charges.index'))->assertForbidden();
});

test('el cliente ve el cobro de una línea suelta, sin proyecto de por medio', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    $hosting = Service::factory()->for($client)->standalone()->create(['name' => 'Hosting anual']);
    Charge::factory()->for($hosting)->pending()->create(['amount' => '1234.00']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.charges.index')
        ->assertSee('Hosting anual')
        ->assertSee('1,234.00');
});

test('lo pagado se separa de lo que sigue debiéndose', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    $service = Service::factory()->for($client)->standalone()->create(['name' => 'Hosting anual']);
    Charge::factory()->for($service)->pending()->create(['concept' => 'Hosting 2026']);
    Charge::factory()->for($service)->paid()->create(['concept' => 'Hosting 2025']);

    $this->actingAs($clientUser);

    $page = Livewire::test('pages::portal.charges.index');

    expect($page->get('pendingCharges')->pluck('concept')->all())->toBe(['Hosting 2026'])
        ->and($page->get('paidCharges')->pluck('concept')->all())->toBe(['Hosting 2025']);

    $page->assertSee('Hosting 2026')->assertSee('Hosting 2025');
});

test('client can only see the charges of their own companies', function () {
    $client = Client::factory()->client()->create();
    $clientUser = User::factory()->client(portalContactFor($client))->create();

    $own = Service::factory()->for($client)->standalone()->create();
    Charge::factory()->for($own)->pending()->create(['concept' => 'Cobro propio']);

    $foreign = Service::factory()->standalone()->create();
    Charge::factory()->for($foreign)->pending()->create(['concept' => 'Cobro ajeno']);

    $this->actingAs($clientUser);

    Livewire::test('pages::portal.charges.index')
        ->assertSee('Cobro propio')
        ->assertDontSee('Cobro ajeno');
});
