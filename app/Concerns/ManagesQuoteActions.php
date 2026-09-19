<?php

namespace App\Concerns;

use App\Actions\Quotes\AcceptQuote;
use App\Actions\Quotes\RejectQuote;
use App\Actions\Quotes\SendQuote;
use App\Actions\Quotes\UndoAcceptQuote;
use App\Enums\QuoteStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use RuntimeException;

/**
 * Todo lo que se le puede hacer a una cotización ya existente —editarla,
 * mandarla, aceptarla o rechazarla, y deshacer cualquiera de esas tres por si
 * se marcó por error—, más el texto para copiarla. Lo comparten `QuotesPanel`
 * (acotado a un cliente, y a veces a un proyecto) y el listado general de
 * `/cotizaciones` (sin acotar a nadie), así que cada acción autoriza contra
 * el cliente de la cotización encontrada, no contra un cliente fijo del
 * componente.
 *
 * Quien lo use en un contexto que además sabe crear cotizaciones nuevas
 * sobrescribe `newQuoteClient()` (y `newQuoteProjectId()` si aplica); el
 * listado general nunca crea desde aquí —eso vive en su propio flujo de
 * "Nueva cotización", que resuelve el cliente primero— así que ahí esos dos
 * métodos nunca se llegan a invocar.
 */
trait ManagesQuoteActions
{
    public ?int $editingQuoteId = null;

    /**
     * El proyecto al que ya pertenece (o pertenecerá) la cotización en
     * edición, si lo hay. Determina si sus renglones pueden ser de categoría
     * de dominio (hosting/SSL/dominio/correo nunca cuelgan de un proyecto).
     */
    public ?int $editingQuoteProjectId = null;

    public string $quoteName = '';

    public ?string $quoteDescription = null;

    public string $quoteCurrency = 'MXN';

    public ?string $quoteValidUntil = null;

    public ?string $quoteNotes = null;

    /**
     * Renglones de la cotización en edición: cada uno es un concepto con su
     * propia categoría, frecuencia y monto.
     *
     * @var array<int, array{id: int|null, name: string, description: string|null, category: string, billing_frequency: string, amount: string}>
     */
    public array $lineItems = [];

    public ?int $rejectingQuoteId = null;

    public ?string $rejectionReason = null;

    public ?int $acceptingQuoteId = null;

    /**
     * Se decide hasta aquí, no al capturar: al aceptar es cuando de verdad se
     * sabe si el trabajo amerita su propio proyecto.
     */
    public bool $acceptAsProject = false;

    /**
     * Hosting, SSL, dominio y correo cuelgan del cliente y no del proyecto,
     * así que ni se ofrecen como categoría de renglón cuando la cotización en
     * edición ya es (o será) de un proyecto.
     *
     * @return array<int, ServiceCategory>
     */
    #[Computed]
    public function categoryOptions(): array
    {
        return collect(ServiceCategory::cases())
            ->reject(fn (ServiceCategory $category) => $this->editingQuoteProjectId !== null && $category->belongsToDomain())
            ->values()
            ->all();
    }

    /**
     * @return array<int, ServiceBillingFrequency>
     */
    #[Computed]
    public function frequencyOptions(): array
    {
        return ServiceBillingFrequency::cases();
    }

    /**
     * Suma en vivo de los renglones del formulario, para ver el total sin
     * guardar.
     */
    public function lineItemsTotal(): float
    {
        return collect($this->lineItems)->sum(fn (array $item) => (float) ($item['amount'] ?: 0));
    }

    public function openQuoteModal(?int $quoteId = null): void
    {
        $this->resetValidation();
        $this->editingQuoteId = $quoteId;

        if ($quoteId === null) {
            $client = $this->newQuoteClient();

            Gate::authorize('update', $client);

            $this->quoteName = '';
            $this->quoteDescription = null;
            $this->quoteCurrency = $client->currency;
            $this->quoteValidUntil = today()->addDays(30)->toDateString();
            $this->quoteNotes = null;
            $this->editingQuoteProjectId = $this->newQuoteProjectId();
            $this->lineItems = [$this->blankLineItem()];
        } else {
            $quote = $this->findQuoteForAction($quoteId);

            Gate::authorize('update', $quote->client);

            $this->quoteName = $quote->name;
            $this->quoteDescription = $quote->description;
            $this->quoteCurrency = $quote->currency;
            $this->quoteValidUntil = $quote->valid_until?->toDateString();
            $this->quoteNotes = $quote->notes;
            $this->editingQuoteProjectId = $quote->project_id;
            $this->lineItems = $quote->lineItems->map(fn (QuoteLineItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'category' => $item->category->value,
                'billing_frequency' => $item->billing_frequency->value,
                'amount' => $item->amount,
            ])->all();
        }

        $this->modal('quote-form')->show();
    }

    /**
     * @return array{id: null, name: string, description: null, category: string, billing_frequency: string, amount: string}
     */
    private function blankLineItem(): array
    {
        return [
            'id' => null,
            'name' => '',
            'description' => null,
            'category' => ServiceCategory::Other->value,
            'billing_frequency' => ServiceBillingFrequency::OneTime->value,
            'amount' => '',
        ];
    }

    public function addLineItem(): void
    {
        $this->lineItems[] = $this->blankLineItem();
    }

    public function removeLineItem(int $index): void
    {
        if (count($this->lineItems) <= 1) {
            return;
        }

        unset($this->lineItems[$index]);

        $this->lineItems = array_values($this->lineItems);
    }

    public function saveQuote(): void
    {
        $quote = $this->editingQuoteId !== null ? $this->findQuoteForAction($this->editingQuoteId) : null;

        Gate::authorize('update', $quote->client ?? $this->newQuoteClient());

        $validated = $this->validate([
            'quoteName' => ['required', 'string', 'max:255'],
            'quoteDescription' => ['nullable', 'string', 'max:2000'],
            'quoteCurrency' => ['required', 'string', 'size:3'],
            'quoteValidUntil' => ['nullable', 'date'],
            'quoteNotes' => ['nullable', 'string', 'max:2000'],
            'lineItems' => ['required', 'array', 'min:1'],
            'lineItems.*.name' => ['required', 'string', 'max:255'],
            'lineItems.*.description' => ['nullable', 'string', 'max:2000'],
            'lineItems.*.category' => [
                'required',
                Rule::enum(ServiceCategory::class),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->editingQuoteProjectId !== null && ServiceCategory::from($value)->belongsToDomain()) {
                        $fail(__('Esta categoría cuelga del cliente, no de un proyecto.'));
                    }
                },
            ],
            'lineItems.*.billing_frequency' => ['required', Rule::enum(ServiceBillingFrequency::class)],
            'lineItems.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        // "Es un proyecto" no se captura ni se edita aquí: se pregunta hasta
        // que se acepta, que es cuando de verdad se sabe si el trabajo
        // amerita uno. `is_project` no se toca al guardar, así que una
        // cotización ya aceptada conserva lo que se decidió al aceptarla.
        $attributes = [
            'name' => $validated['quoteName'],
            'description' => $validated['quoteDescription'],
            'currency' => $validated['quoteCurrency'],
            'valid_until' => $validated['quoteValidUntil'],
            'notes' => $validated['quoteNotes'],
        ];

        if ($quote !== null) {
            $quote->update($attributes);
            $this->syncLineItems($quote, $validated['lineItems']);
        } else {
            $quote = $this->newQuoteClient()->quotes()->create([
                ...$attributes,
                'project_id' => $this->newQuoteProjectId(),
                'status' => QuoteStatus::Borrador,
            ]);
            $this->syncLineItems($quote, $validated['lineItems']);
        }

        $this->refreshQuoteLists();

        $this->modal('quote-form')->close();

        Flux::toast(variant: 'success', text: __('Cotización guardada.'));
    }

    /**
     * Actualiza los renglones que ya existían, crea los nuevos y borra los que
     * quitaron del formulario. Un renglón que ya generó línea cobrable nunca
     * se borra aquí: seguiría siendo la constancia de esa línea.
     *
     * @param  array<int, array{id: int|null, name: string, description: string|null, category: string, billing_frequency: string, amount: string}>  $items
     */
    private function syncLineItems(Quote $quote, array $items): void
    {
        $keptIds = [];

        foreach ($items as $item) {
            $itemAttributes = [
                'name' => $item['name'],
                'description' => $item['description'],
                'category' => $item['category'],
                'billing_frequency' => $item['billing_frequency'],
                'amount' => $item['amount'],
            ];

            if (! empty($item['id'])) {
                $quote->lineItems()->whereKey($item['id'])->update($itemAttributes);
                $keptIds[] = $item['id'];
            } else {
                $keptIds[] = $quote->lineItems()->create($itemAttributes)->id;
            }
        }

        $quote->lineItems()->whereNotIn('id', $keptIds)->whereNull('service_id')->delete();
    }

    public function closeQuoteModal(): void
    {
        $this->modal('quote-form')->close();
    }

    /**
     * Dentro de un proyecto la pregunta de si abre proyecto no aplica —lo
     * cotizado ya es de ese trabajo—, así que ahí se acepta directo.
     */
    public function accept(int $quoteId, AcceptQuote $action): void
    {
        $quote = $this->findQuoteForAction($quoteId);

        Gate::authorize('update', $quote->client);

        $this->finishAccepting($quote, $action);
    }

    /**
     * Fuera de un proyecto, aceptar es el momento en que de verdad se sabe si
     * el trabajo amerita abrir uno: se pregunta aquí, no al capturar la
     * cotización.
     */
    public function openAcceptModal(int $quoteId): void
    {
        $quote = $this->findQuoteForAction($quoteId);

        Gate::authorize('update', $quote->client);

        $this->acceptingQuoteId = $quote->id;
        $this->acceptAsProject = $quote->is_project;

        $this->modal('quote-accept')->show();
    }

    public function confirmAccept(AcceptQuote $action): void
    {
        $quote = $this->findQuoteForAction($this->acceptingQuoteId ?? 0);

        Gate::authorize('update', $quote->client);

        $quote->update(['is_project' => $this->acceptAsProject]);

        $this->finishAccepting($quote, $action);

        $this->acceptingQuoteId = null;

        $this->modal('quote-accept')->close();
    }

    public function closeAcceptModal(): void
    {
        $this->acceptingQuoteId = null;

        $this->modal('quote-accept')->close();
    }

    private function finishAccepting(Quote $quote, AcceptQuote $action): void
    {
        $action->handle($quote, auth()->user());

        $this->refreshQuoteLists();

        /**
         * Aceptar crea cosas que se pintan fuera de este componente —el
         * proyecto, la línea cobrable, sus cobros—, y esos paneles no se
         * enteran solos: sin el aviso había que recargar la ficha para verlas.
         */
        $this->dispatch('quote-accepted');

        Flux::toast(variant: 'success', text: __('Cotización aceptada: ya existen sus líneas cobrables.'));
    }

    /**
     * Por si se marcó como enviada por error: no toca el estatus del
     * prospecto que `SendQuote` haya movido, eso se corrige aparte si hace
     * falta.
     */
    public function undoSend(int $quoteId): void
    {
        $quote = $this->findQuoteForAction($quoteId);

        Gate::authorize('update', $quote->client);

        if ($quote->status !== QuoteStatus::Enviada) {
            return;
        }

        $quote->update([
            'status' => QuoteStatus::Borrador,
            'sent_at' => null,
        ]);

        $this->refreshQuoteLists();

        Flux::toast(variant: 'success', text: __('Cotización regresada a borrador.'));
    }

    /**
     * Por si se marcó como aceptada por error: borra las líneas cobrables que
     * generó, salvo que alguna ya tenga un cobro con abono -en ese caso hay
     * que cancelarla a mano en vez de deshacer aquí.
     */
    public function undoAccept(int $quoteId, UndoAcceptQuote $action): void
    {
        $quote = $this->findQuoteForAction($quoteId);

        Gate::authorize('update', $quote->client);

        if ($quote->status !== QuoteStatus::Aceptada) {
            return;
        }

        try {
            $action->handle($quote);
        } catch (RuntimeException) {
            Flux::toast(variant: 'danger', text: __('No se puede deshacer: ya tiene cobros con abonos. Cancélalos desde Trabajos y cobros si ya no aplican.'));

            return;
        }

        $this->refreshQuoteLists();

        /** Deshacer borra líneas, cobros y quizás el vínculo a un proyecto: los paneles que los pintan necesitan enterarse igual que al aceptar. */
        $this->dispatch('quote-undone');

        Flux::toast(variant: 'success', text: __('Aceptación deshecha: se borraron sus líneas cobrables.'));
    }

    public function send(int $quoteId, SendQuote $action): void
    {
        $quote = $this->findQuoteForAction($quoteId);

        Gate::authorize('update', $quote->client);

        $action->handle($quote, auth()->user());

        $this->refreshQuoteLists();

        Flux::toast(variant: 'success', text: __('Cotización marcada como enviada.'));
    }

    public function openRejectModal(int $quoteId): void
    {
        $quote = $this->findQuoteForAction($quoteId);

        Gate::authorize('update', $quote->client);

        $this->rejectingQuoteId = $quote->id;
        $this->rejectionReason = null;
        $this->resetValidation();

        $this->modal('quote-rejection')->show();
    }

    public function reject(RejectQuote $action): void
    {
        $quote = $this->findQuoteForAction($this->rejectingQuoteId ?? 0);

        Gate::authorize('update', $quote->client);

        $validated = $this->validate([
            'rejectionReason' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->handle($quote, $validated['rejectionReason']);

        $this->refreshQuoteLists();

        $this->rejectingQuoteId = null;

        $this->modal('quote-rejection')->close();

        Flux::toast(variant: 'success', text: __('Cotización marcada como rechazada.'));
    }

    public function closeRejectModal(): void
    {
        $this->rejectingQuoteId = null;

        $this->modal('quote-rejection')->close();
    }

    /**
     * Por si se marcó como rechazada por error: la regresa a como estaba
     * antes de decidirse -enviada si ya se había mandado, borrador si no.
     */
    public function undoReject(int $quoteId): void
    {
        $quote = $this->findQuoteForAction($quoteId);

        Gate::authorize('update', $quote->client);

        if ($quote->status !== QuoteStatus::Rechazada) {
            return;
        }

        $quote->update([
            'status' => $quote->sent_at !== null ? QuoteStatus::Enviada : QuoteStatus::Borrador,
            'decided_at' => null,
        ]);

        $this->refreshQuoteLists();

        Flux::toast(variant: 'success', text: __('Cotización reabierta.'));
    }

    public function deleteQuote(int $quoteId): void
    {
        $quote = $this->findQuoteForAction($quoteId);

        Gate::authorize('update', $quote->client);

        if ($quote->lineItems()->whereNotNull('service_id')->exists()) {
            Flux::toast(variant: 'danger', text: __('Esta cotización ya generó una línea cobrable. Bórrala desde ahí si fue un error.'));

            return;
        }

        $quote->delete();

        $this->refreshQuoteLists();

        Flux::toast(variant: 'success', text: __('Cotización eliminada.'));
    }

    /**
     * El http completo del enlace público según `APP_URL`, no según el host
     * de la petición: en LERD (y detrás de cualquier proxy) el request puede
     * llegar con un host distinto al dominio público, y este es el enlace
     * que de verdad se le manda al cliente para que vea la cotización y
     * decida.
     */
    public function quotePublicUrl(Quote $quote): string
    {
        $path = route('quotes.public', ['quote' => $quote->public_token], absolute: false);

        return rtrim(config('app.url'), '/').$path;
    }

    /**
     * El copiado en sí lo hace Alpine en el navegador (`navigator.clipboard`);
     * esto solo confirma que ya quedó en el portapapeles. Recibe el id sin
     * usarlo más que para eso: sin él, todas las filas comparten el mismo
     * `wire:target` derivado de "markCopied" y Flux marca como "cargando" el
     * botón de copiar de toda la tabla en vez de solo el que se pulsó.
     */
    public function markCopied(int $quoteId): void
    {
        Flux::toast(variant: 'success', text: __('Copiada al portapapeles.'));
    }

    /**
     * El id llega del navegador, así que quien viva acotado a un cliente (y
     * quizás a un proyecto) lo busca dentro de ese alcance.
     */
    protected function findQuoteForAction(int $quoteId): Quote
    {
        return Quote::findOrFail($quoteId);
    }

    /**
     * Invalida lo que haya cacheado en memoria para que la lista se vuelva a
     * calcular con el cambio recién hecho.
     */
    protected function refreshQuoteLists(): void
    {
        unset($this->quotes);
    }

    /**
     * El cliente al que pertenecerá una cotización nueva. Solo lo necesita
     * quien ofrece capturar una desde cero (`openQuoteModal(null)`); el
     * listado general nunca lo hace -ahí "Nueva cotización" resuelve primero
     * el cliente y manda a su ficha- así que ahí este método nunca se llega a
     * invocar.
     */
    protected function newQuoteClient(): Client
    {
        throw new RuntimeException('Este contexto no captura cotizaciones nuevas.');
    }

    protected function newQuoteProjectId(): ?int
    {
        return null;
    }
}
