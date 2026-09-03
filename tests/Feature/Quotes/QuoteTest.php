<?php

use App\Actions\Quotes\AcceptQuote;
use App\Actions\Quotes\RejectQuote;
use App\Actions\Quotes\SendQuote;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\QuoteStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Livewire\QuotesPanel;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('una cotización se captura desde la ficha del cliente y nace en borrador, sin generar cobros', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('openQuoteModal')
        ->set('quoteName', 'Sitio web institucional')
        ->set('quoteAmount', '38000')
        ->call('saveQuote')
        ->assertHasNoErrors();

    $quote = $client->quotes()->firstOrFail();

    expect($quote->status)->toBe(QuoteStatus::Borrador)
        ->and($quote->service_id)->toBeNull()
        ->and($client->services()->count())->toBe(0);
});

test('aceptar una cotización genera su línea cobrable con lo cotizado', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->create([
        'name' => 'Mejora continua del sitio',
        'amount' => '5500.00',
        'billing_frequency' => ServiceBillingFrequency::Monthly,
    ]);

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('accept', $quote->id);

    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Aceptada)
        ->and($quote->decided_at)->not->toBeNull()
        ->and($quote->service)->not->toBeNull()
        ->and((float) $quote->service->amount)->toBe(5500.0)
        ->and($quote->service->billing_frequency)->toBe(ServiceBillingFrequency::Monthly)
        ->and($quote->service->charges()->count())->toBe(1);
});

test('una cotización marcada como proyecto abre el proyecto al aceptarse y mete ahí la línea', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->asProject()->create([
        'name' => 'Sitio web institucional',
        'category' => ServiceCategory::Website,
    ]);

    app(AcceptQuote::class)->handle($quote, $staff);

    $quote->refresh();

    expect($client->projects()->count())->toBe(1)
        ->and($quote->project)->not->toBeNull()
        ->and($quote->project->name)->toBe('Sitio web institucional')
        ->and($quote->project->type)->toBe(ProjectType::Web)
        ->and($quote->project->status)->toBe(ProjectStatus::Activo)
        ->and($quote->service->project_id)->toBe($quote->project->id);
});

test('una cotización sin marcar como proyecto nace como línea suelta del cliente', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->create();

    app(AcceptQuote::class)->handle($quote, $staff);

    $quote->refresh();

    expect($client->projects()->count())->toBe(0)
        ->and($quote->project_id)->toBeNull()
        ->and($quote->service->project_id)->toBeNull();
});

test('el switch del formulario es lo que deja la cotización marcada como proyecto', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('openQuoteModal')
        ->assertSet('quoteIsProject', false)
        ->set('quoteName', 'Rediseño completo')
        ->set('quoteAmount', '80000')
        ->set('quoteIsProject', true)
        ->call('saveQuote')
        ->assertHasNoErrors();

    expect($client->quotes()->firstOrFail()->is_project)->toBeTrue();
});

test('aceptar la cotización de un prospecto lo gana y lo convierte en cliente', function () {
    $staff = User::factory()->staff()->create();
    $prospect = Client::factory()->prospect()->create();

    $quote = Quote::factory()->for($prospect)->sent()->create();

    app(AcceptQuote::class)->handle($quote, $staff);

    $prospect->refresh();

    expect($prospect->type)->toBe(ClientType::Client)
        ->and($prospect->status)->toBe(ClientStatus::Ganado)
        ->and($prospect->won_at)->not->toBeNull();
});

test('marcar enviada una cotización mueve al prospecto a propuesta enviada', function () {
    $staff = User::factory()->staff()->create();
    $prospect = Client::factory()->prospect()->create(['status' => ClientStatus::Contactado]);

    $quote = Quote::factory()->for($prospect)->create();

    app(SendQuote::class)->handle($quote, $staff);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Enviada)
        ->and($quote->fresh()->sent_at)->not->toBeNull()
        ->and($prospect->fresh()->status)->toBe(ClientStatus::PropuestaEnviada);
});

test('rechazar guarda la razón y no toca el estatus del prospecto', function () {
    $prospect = Client::factory()->prospect()->create(['status' => ClientStatus::PropuestaEnviada]);
    $quote = Quote::factory()->for($prospect)->sent()->create();

    app(RejectQuote::class)->handle($quote, 'Se fue con otro proveedor.');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Rechazada)
        ->and($quote->fresh()->notes)->toBe('Se fue con otro proveedor.')
        ->and($prospect->fresh()->status)->toBe(ClientStatus::PropuestaEnviada);
});

test('la corrida diaria expira las cotizaciones enviadas cuya vigencia pasó', function () {
    $client = Client::factory()->client()->create();

    $stale = Quote::factory()->for($client)->expiring()->create();
    $fresh = Quote::factory()->for($client)->sent()->create(['valid_until' => today()->addDays(10)->toDateString()]);
    $draft = Quote::factory()->for($client)->create(['valid_until' => today()->subDay()->toDateString()]);

    $this->artisan('charges:process')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(QuoteStatus::Expirada)
        ->and($fresh->fresh()->status)->toBe(QuoteStatus::Enviada)
        /** Un borrador no expira: nunca salió, así que no hay nada que caduque. */
        ->and($draft->fresh()->status)->toBe(QuoteStatus::Borrador);
});

test('una cotización que ya generó línea cobrable no se borra', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->sent()->create();

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('accept', $quote->id)
        ->call('deleteQuote', $quote->id);

    expect(Quote::find($quote->id))->not->toBeNull();
});

test('no se puede tocar la cotización de otro cliente desde este panel', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $foreign = Quote::factory()->for(Client::factory()->client())->create();

    $this->actingAs($staff);

    expect(fn () => Livewire::test(QuotesPanel::class, ['client' => $client])->call('accept', $foreign->id))
        ->toThrow(ModelNotFoundException::class);
});

test('el listado de cotizaciones filtra y suma lo que está en juego', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create(['name' => 'Clínica Sur']);
    $other = Client::factory()->client()->create(['name' => 'Tacos El Güero']);

    Quote::factory()->for($client)->sent()->create(['name' => 'Sitio institucional', 'amount' => '38000.00']);
    Quote::factory()->for($other)->sent()->create(['name' => 'Menú digital', 'amount' => '12000.00']);

    $this->actingAs($staff);

    Livewire::test('pages::quotes.index')
        ->assertSee('Sitio institucional')
        ->assertSee('Menú digital')
        ->assertSee('50,000.00')
        ->set('clientFilter', $client->id)
        ->assertSee('Sitio institucional')
        ->assertDontSee('Menú digital');
});

test('un colaborador no entra a cotizaciones', function () {
    $collaborator = User::factory()->collaborator()->create();

    $this->actingAs($collaborator);

    $this->get(route('quotes.index'))->assertForbidden();
});
