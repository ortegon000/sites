<?php

use App\Actions\Charges\MarkChargeAsPaid;
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
use App\Livewire\ChargesPanel;
use App\Livewire\ProjectsPanel;
use App\Livewire\QuotesPanel;
use App\Models\Client;
use App\Models\Project;
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
        ->set('lineItems.0.name', 'Diseño y desarrollo')
        ->set('lineItems.0.amount', '38000')
        ->call('saveQuote')
        ->assertHasNoErrors();

    $quote = $client->quotes()->firstOrFail();

    expect($quote->status)->toBe(QuoteStatus::Borrador)
        ->and($quote->lineItems()->count())->toBe(1)
        ->and($quote->lineItems()->whereNotNull('service_id')->count())->toBe(0)
        ->and($client->services()->count())->toBe(0);
});

test('una cotización puede tener varios renglones, cada uno con su propia categoría y frecuencia', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('openQuoteModal')
        ->set('quoteName', 'Sitio web + hosting')
        ->set('lineItems.0.name', 'Diseño y desarrollo')
        ->set('lineItems.0.amount', '38000')
        ->call('addLineItem')
        ->set('lineItems.1.name', 'Hosting anual')
        ->set('lineItems.1.category', ServiceCategory::Hosting->value)
        ->set('lineItems.1.billing_frequency', ServiceBillingFrequency::Annual->value)
        ->set('lineItems.1.amount', '1800')
        ->call('saveQuote')
        ->assertHasNoErrors();

    $quote = $client->quotes()->firstOrFail();

    expect($quote->lineItems()->count())->toBe(2)
        ->and($quote->lineItems()->where('category', ServiceCategory::Hosting)->exists())->toBeTrue();
});

test('aceptar una cotización genera una línea cobrable por cada renglón', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->withLineItem([
        'name' => 'Mejora continua del sitio',
        'amount' => '5500.00',
        'billing_frequency' => ServiceBillingFrequency::Monthly,
    ])->create();

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('accept', $quote->id);

    $quote->refresh()->load('lineItems.service');
    $lineItem = $quote->lineItems->first();

    expect($quote->status)->toBe(QuoteStatus::Aceptada)
        ->and($quote->decided_at)->not->toBeNull()
        ->and($lineItem->service)->not->toBeNull()
        ->and((float) $lineItem->service->amount)->toBe(5500.0)
        ->and($lineItem->service->billing_frequency)->toBe(ServiceBillingFrequency::Monthly)
        ->and($lineItem->service->charges()->count())->toBe(1);
});

test('una cotización marcada como proyecto abre el proyecto al aceptarse y mete ahí las líneas', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->asProject()->withLineItem([
        'category' => ServiceCategory::Website,
    ])->create(['name' => 'Sitio web institucional']);

    app(AcceptQuote::class)->handle($quote, $staff);

    $quote->refresh()->load('lineItems.service', 'project');
    $lineItem = $quote->lineItems->first();

    expect($client->projects()->count())->toBe(1)
        ->and($quote->project)->not->toBeNull()
        ->and($quote->project->name)->toBe('Sitio web institucional')
        ->and($quote->project->type)->toBe(ProjectType::Web)
        ->and($quote->project->status)->toBe(ProjectStatus::Activo)
        ->and($lineItem->service->project_id)->toBe($quote->project->id);
});

test('una cotización sin marcar como proyecto nace como líneas sueltas del cliente', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->withLineItem()->create();

    app(AcceptQuote::class)->handle($quote, $staff);

    $quote->refresh()->load('lineItems.service');

    expect($client->projects()->count())->toBe(0)
        ->and($quote->project_id)->toBeNull()
        ->and($quote->lineItems->first()->service->project_id)->toBeNull();
});

test('una cotización solo de renglones de dominio marcada como proyecto no abre proyecto al aceptarse', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->asProject()->withLineItem([
        'category' => ServiceCategory::Domain,
    ])->create();

    app(AcceptQuote::class)->handle($quote, $staff);

    $quote->refresh();

    expect($client->projects()->count())->toBe(0)
        ->and($quote->project_id)->toBeNull();
});

test('un renglón de dominio dentro de una cotización de proyecto no cuelga del proyecto que sí abre', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->asProject()
        ->withLineItem(['category' => ServiceCategory::Website])
        ->withLineItem(['category' => ServiceCategory::Hosting])
        ->create();

    app(AcceptQuote::class)->handle($quote, $staff);

    $quote->refresh()->load('lineItems.service', 'project');

    $websiteService = $quote->lineItems->firstWhere('category', ServiceCategory::Website)->service;
    $hostingService = $quote->lineItems->firstWhere('category', ServiceCategory::Hosting)->service;

    expect($websiteService->project_id)->toBe($quote->project->id)
        ->and($hostingService->project_id)->toBeNull();
});

test('una cotización nueva nunca nace marcada como proyecto: eso se decide hasta aceptarla', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('openQuoteModal')
        ->set('quoteName', 'Rediseño completo')
        ->set('lineItems.0.name', 'Rediseño completo')
        ->set('lineItems.0.amount', '80000')
        ->call('saveQuote')
        ->assertHasNoErrors();

    expect($client->quotes()->firstOrFail()->is_project)->toBeFalse();
});

test('editar una cotización ya aceptada no le toca si es proyecto o línea suelta', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->asProject()->withLineItem([
        'category' => ServiceCategory::Website,
    ])->create(['name' => 'Rediseño completo']);

    app(AcceptQuote::class)->handle($quote, $staff);

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('openQuoteModal', $quote->id)
        ->set('quoteName', 'Rediseño completo v2')
        ->call('saveQuote')
        ->assertHasNoErrors();

    expect($quote->fresh()->is_project)->toBeTrue();
});

test('aceptar una cotización es donde se pregunta si es proyecto o línea suelta', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->withLineItem([
        'category' => ServiceCategory::Website,
    ])->create();

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('openAcceptModal', $quote->id)
        ->assertSet('acceptAsProject', false)
        ->set('acceptAsProject', true)
        ->call('confirmAccept')
        ->assertDispatched('quote-accepted');

    expect($quote->fresh()->is_project)->toBeTrue()
        ->and($client->projects()->count())->toBe(1);
});

test('una cotización de hosting, ssl, dominio o correo no se puede capturar dentro de un proyecto', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client, 'project' => $project])
        ->call('openQuoteModal')
        ->set('quoteName', 'Hosting anual')
        ->set('lineItems.0.name', 'Hosting anual')
        ->set('lineItems.0.category', ServiceCategory::Hosting->value)
        ->set('lineItems.0.amount', '3800')
        ->call('saveQuote')
        ->assertHasErrors('lineItems.0.category');

    expect(Quote::where('project_id', $project->id)->count())->toBe(0);
});

test('lo que crea una cotización aceptada aparece sin recargar la ficha', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->asProject()->withLineItem([
        'category' => ServiceCategory::Website,
    ])->create(['name' => 'Sitio web institucional']);

    $this->actingAs($staff);

    /** Las tarjetas de la ficha escuchan el aviso: hasta que la cotización se acepta, ahí no hay proyecto ni cobro. */
    $proyectos = Livewire::test(ProjectsPanel::class, ['client' => $client])
        ->assertSee('No todos los clientes necesitan uno', escape: false);

    $cobros = Livewire::test(ChargesPanel::class, ['client' => $client])
        ->assertDontSee('Sitio web institucional');

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('accept', $quote->id)
        ->assertDispatched('quote-accepted');

    $proyectos->dispatch('quote-accepted')
        ->assertDontSee('No todos los clientes necesitan uno', escape: false)
        ->assertSee('Sitio web institucional');

    $cobros->dispatch('quote-accepted')
        ->assertSee('Sitio web institucional');
});

test('el panel abre en pendientes y manda lo decidido a archivadas', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    Quote::factory()->for($client)->sent()->create(['name' => 'Esperando respuesta']);
    Quote::factory()->for($client)->create(['name' => 'Ya aceptada', 'status' => QuoteStatus::Aceptada]);
    Quote::factory()->for($client)->create(['name' => 'Ya rechazada', 'status' => QuoteStatus::Rechazada]);
    Quote::factory()->for($client)->expiring()->create(['name' => 'Se le pasó la fecha', 'status' => QuoteStatus::Expirada]);

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->assertSet('quotesTab', 'pendientes')
        ->assertSee('Esperando respuesta')
        ->assertDontSee('Ya aceptada')
        ->assertDontSee('Ya rechazada')
        ->assertDontSee('Se le pasó la fecha')
        ->set('quotesTab', 'archivadas')
        ->assertDontSee('Esperando respuesta')
        ->assertSee('Ya aceptada')
        ->assertSee('Ya rechazada')
        /** La expirada vive en archivadas: si no, no se vería en ninguna de las dos listas. */
        ->assertSee('Se le pasó la fecha');
});

test('aceptar una cotización la saca de pendientes', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $quote = Quote::factory()->for($client)->sent()->withLineItem()->create(['name' => 'Mejora continua del sitio']);

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->assertSee('Mejora continua del sitio')
        ->call('accept', $quote->id)
        ->assertDontSee('Mejora continua del sitio')
        ->set('quotesTab', 'archivadas')
        ->assertSee('Mejora continua del sitio');
});

test('aceptar la cotización de un prospecto lo gana y lo convierte en cliente', function () {
    $staff = User::factory()->staff()->create();
    $prospect = Client::factory()->prospect()->create();

    $quote = Quote::factory()->for($prospect)->sent()->withLineItem()->create();

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
    $quote = Quote::factory()->for($client)->sent()->withLineItem()->create();

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

    Quote::factory()->for($client)->sent()->withLineItem(['amount' => '38000.00'])->create(['name' => 'Sitio institucional']);
    Quote::factory()->for($other)->sent()->withLineItem(['amount' => '12000.00'])->create(['name' => 'Menú digital']);

    $this->actingAs($staff);

    Livewire::test('pages::quotes.index')
        ->assertSee('Sitio institucional')
        ->assertSee('Menú digital')
        ->assertSee('50,000.00')
        ->set('clientFilter', $client->id)
        ->assertSee('Sitio institucional')
        ->assertDontSee('Menú digital');
});

test('desde el listado general se puede editar una cotización de cualquier cliente', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->withLineItem(['amount' => '1000.00'])->create(['name' => 'Original']);

    $this->actingAs($staff);

    Livewire::test('pages::quotes.index')
        ->call('openQuoteModal', $quote->id)
        ->assertSet('quoteName', 'Original')
        ->set('quoteName', 'Editada desde el listado')
        ->call('saveQuote')
        ->assertHasNoErrors();

    expect($quote->fresh()->name)->toBe('Editada desde el listado');
});

test('desde el listado general se puede enviar, aceptar, rechazar y deshacer', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->withLineItem()->create();

    $this->actingAs($staff);

    $component = Livewire::test('pages::quotes.index')
        ->call('send', $quote->id);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Enviada);

    $component->call('undoSend', $quote->id);
    expect($quote->fresh()->status)->toBe(QuoteStatus::Borrador);

    $component->call('send', $quote->id)
        ->call('openAcceptModal', $quote->id)
        ->assertSet('acceptAsProject', false)
        ->call('confirmAccept')
        ->assertDispatched('quote-accepted');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Aceptada)
        ->and($client->services()->count())->toBe(1);

    $component->call('undoAccept', $quote->id)
        ->assertDispatched('quote-undone');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Enviada)
        ->and($client->services()->count())->toBe(0);

    $component->call('openRejectModal', $quote->id)
        ->set('rejectionReason', 'Se fue con otro proveedor.')
        ->call('reject');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Rechazada);

    $component->call('undoReject', $quote->id);
    expect($quote->fresh()->status)->toBe(QuoteStatus::Enviada);

    $component->call('deleteQuote', $quote->id);
    expect(Quote::find($quote->id))->toBeNull();
});

test('un colaborador no entra a cotizaciones', function () {
    $collaborator = User::factory()->collaborator()->create();

    $this->actingAs($collaborator);

    $this->get(route('quotes.index'))->assertForbidden();
});

test('nueva cotización para un cliente existente manda a su ficha con el formulario abierto', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test('pages::quotes.index')
        ->set('newQuoteTarget', (string) $client->id)
        ->call('startNewQuote')
        ->assertRedirect(route('clients.show', [
            'client' => $client,
            'seccion' => 'trabajo',
            'nueva_cotizacion' => 1,
        ]));
});

test('nueva cotización para un prospecto existente manda a su ficha de prospecto', function () {
    $staff = User::factory()->staff()->create();
    $prospect = Client::factory()->prospect()->create();

    $this->actingAs($staff);

    Livewire::test('pages::quotes.index')
        ->set('newQuoteTarget', (string) $prospect->id)
        ->call('startNewQuote')
        ->assertRedirect(route('prospects.show', [
            'client' => $prospect,
            'seccion' => 'trabajo',
            'nueva_cotizacion' => 1,
        ]));
});

test('nueva cotización para alguien que no existe crea un prospecto con lo mínimo y manda a su ficha', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff);

    Livewire::test('pages::quotes.index')
        ->set('newQuoteTarget', 'new')
        ->set('newQuoteProspectName', 'Café Central')
        ->call('startNewQuote')
        ->assertHasNoErrors();

    $prospect = Client::where('name', 'Café Central')->firstOrFail();

    expect($prospect->type)->toBe(ClientType::Prospect)
        ->and($prospect->status)->toBe(ClientStatus::Nuevo)
        ->and($prospect->currency)->toBe('MXN')
        ->and($prospect->assigned_to_user_id)->toBe($staff->id);
});

test('nueva cotización para alguien nuevo exige el nombre del prospecto', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff);

    Livewire::test('pages::quotes.index')
        ->set('newQuoteTarget', 'new')
        ->call('startNewQuote')
        ->assertHasErrors('newQuoteProspectName');

    expect(Client::count())->toBe(0);
});

test('llegar a la ficha con la bandera nueva_cotización abre el formulario de captura', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::withQueryParams(['nueva_cotizacion' => '1'])
        ->test(QuotesPanel::class, ['client' => $client])
        ->assertSet('editingQuoteId', null)
        ->assertCount('lineItems', 1);
});

test('sin la bandera nueva_cotización el formulario no se abre solo', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->assertCount('lineItems', 0);
});

test('deshacer el envío regresa la cotización a borrador', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->sent()->create();

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('undoSend', $quote->id);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Borrador)
        ->and($quote->fresh()->sent_at)->toBeNull();
});

test('deshacer el rechazo regresa a enviada si ya se había enviado', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->sent()->create();

    app(RejectQuote::class)->handle($quote, null);

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('undoReject', $quote->id);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Enviada)
        ->and($quote->fresh()->decided_at)->toBeNull();
});

test('deshacer el rechazo regresa a borrador si nunca se había enviado', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->create(['status' => QuoteStatus::Rechazada]);

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('undoReject', $quote->id);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Borrador);
});

test('deshacer una aceptación borra las líneas cobrables y la regresa a enviada', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->sent()->withLineItem()->create();

    app(AcceptQuote::class)->handle($quote, $staff);

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('undoAccept', $quote->id)
        ->assertDispatched('quote-undone');

    $quote->refresh()->load('lineItems');

    expect($quote->status)->toBe(QuoteStatus::Enviada)
        ->and($quote->decided_at)->toBeNull()
        ->and($quote->lineItems->first()->service_id)->toBeNull()
        ->and($client->services()->count())->toBe(0);
});

test('deshacer una aceptación con proyecto lo desliga sin borrarlo', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->sent()->asProject()->withLineItem([
        'category' => ServiceCategory::Website,
    ])->create();

    app(AcceptQuote::class)->handle($quote, $staff);
    $projectId = $quote->fresh()->project_id;

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('undoAccept', $quote->id);

    expect($quote->fresh()->project_id)->toBeNull()
        ->and(Project::find($projectId))->not->toBeNull();
});

test('una aceptación con cobros abonados no se puede deshacer', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $quote = Quote::factory()->for($client)->sent()->withLineItem()->create();

    app(AcceptQuote::class)->handle($quote, $staff);

    $service = $quote->fresh()->lineItems->first()->service;
    app(MarkChargeAsPaid::class)->handle($service->charges->first());

    $this->actingAs($staff);

    Livewire::test(QuotesPanel::class, ['client' => $client])
        ->call('undoAccept', $quote->id)
        ->assertNotDispatched('quote-undone');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Aceptada)
        ->and($client->services()->count())->toBe(1);
});
