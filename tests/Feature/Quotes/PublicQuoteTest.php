<?php

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\QuoteStatus;
use App\Enums\ServiceCategory;
use App\Livewire\QuotesPanel;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Livewire\Livewire;

test('el enlace público de una cotización enviada no pide sesión y muestra sus renglones', function () {
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->sent()->withLineItem(['amount' => '5000.00'])->create();

    $this->get(route('quotes.public', ['quote' => $quote->public_token]))
        ->assertOk()
        ->assertSee($quote->name)
        ->assertSee('5,000.00');
});

test('aceptar desde el enlace público genera las líneas cobrables sin preguntar por proyecto', function () {
    $prospect = Client::factory()->prospect()->create();
    $quote = Quote::factory()->for($prospect)->sent()->withLineItem([
        'category' => ServiceCategory::Website,
        'amount' => '38000.00',
    ])->create();

    Livewire::test('pages::quotes.public', ['quote' => $quote])
        ->call('accept')
        ->assertSee('Ya quedó registrada');

    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Aceptada)
        ->and($quote->project_id)->toBeNull()
        ->and($prospect->fresh()->type)->toBe(ClientType::Client)
        ->and($prospect->fresh()->status)->toBe(ClientStatus::Ganado);
});

test('rechazar desde el enlace público guarda el motivo', function () {
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->sent()->withLineItem()->create();

    Livewire::test('pages::quotes.public', ['quote' => $quote])
        ->set('rejectionReason', 'Ya no lo necesitamos.')
        ->call('reject')
        ->assertSee('no se acepta');

    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Rechazada)
        ->and($quote->notes)->toBe('Ya no lo necesitamos.');
});

test('un borrador no muestra nada que aceptar ni rechazar desde el enlace público', function () {
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->withLineItem()->create();

    Livewire::test('pages::quotes.public', ['quote' => $quote])
        ->assertSee('ya no está disponible')
        ->assertDontSee('Aceptar propuesta');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Borrador);
});

test('aceptar dos veces desde el enlace público no genera líneas cobrables por duplicado', function () {
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->sent()->withLineItem()->create();

    $component = Livewire::test('pages::quotes.public', ['quote' => $quote])
        ->call('accept');

    expect($client->services()->count())->toBe(1);

    $component->call('accept');

    expect($client->services()->count())->toBe(1);
});

test('el enlace que se copia usa APP_URL y no el host de la petición', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->sent()->withLineItem()->create();

    $this->actingAs($staff);

    $url = Livewire::test(QuotesPanel::class, ['client' => $client])
        ->instance()
        ->quotePublicUrl($quote);

    expect($url)->toBe(rtrim(config('app.url'), '/').'/c/'.$quote->public_token);
});
