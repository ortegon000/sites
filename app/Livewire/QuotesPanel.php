<?php

namespace App\Livewire;

use App\Actions\Quotes\AcceptQuote;
use App\Actions\Quotes\RejectQuote;
use App\Actions\Quotes\SendQuote;
use App\Enums\QuoteStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Lo cotizado a un cliente y todavía no aceptado.
 *
 * Vive en la ficha del cliente —y en la del prospecto, que es donde más se
 * usa— porque cotizar pasa antes de que exista proyecto o cobro. Aceptar una
 * cotización es lo que genera, por cada renglón, su línea cobrable.
 */
class QuotesPanel extends Component
{
    public Client $client;

    public ?Project $project = null;

    public ?int $editingQuoteId = null;

    /**
     * Quien acaba de resolver o crear el cliente desde /cotizaciones llega
     * aquí con esta bandera para no tener que volver a buscar el botón.
     */
    #[Url(as: 'nueva_cotizacion', except: false)]
    public bool $nuevaCotizacion = false;

    public string $quoteName = '';

    public ?string $quoteDescription = null;

    public string $quoteCurrency = 'MXN';

    public ?string $quoteValidUntil = null;

    public ?string $quoteNotes = null;

    /**
     * Marcado en el formulario: al aceptarse, lo cotizado abre proyecto en vez
     * de quedar como líneas sueltas del cliente. Sin efecto si ningún
     * renglón describe trabajo de proyecto (todos son hosting/SSL/dominio/
     * correo).
     */
    public bool $quoteIsProject = false;

    /**
     * Renglones de la cotización en edición: cada uno es un concepto con su
     * propia categoría, frecuencia y monto.
     *
     * @var array<int, array{id: int|null, name: string, description: string|null, category: string, billing_frequency: string, amount: string}>
     */
    public array $lineItems = [];

    /**
     * Qué lista se está viendo: lo que sigue esperando respuesta o lo ya
     * decidido. Abre en pendientes, que es sobre lo que se actúa.
     */
    public string $quotesTab = 'pendientes';

    public ?int $rejectingQuoteId = null;

    public ?string $rejectionReason = null;

    public ?int $acceptingQuoteId = null;

    /**
     * Se decide hasta aquí, no al capturar: al aceptar es cuando de verdad se
     * sabe si el trabajo amerita su propio proyecto.
     */
    public bool $acceptAsProject = false;

    public function mount(Client $client, ?Project $project = null): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
        $this->project = $project;
        $this->quoteCurrency = $client->currency;

        if ($this->nuevaCotizacion && Gate::allows('update', $client)) {
            $this->openQuoteModal();
        }
    }

    /**
     * @return Collection<int, Quote>
     */
    #[Computed]
    public function quotes(): Collection
    {
        return $this->quotesQuery()
            ->whereIn('status', $this->statusesOfCurrentTab())
            ->withSum('lineItems as amount_total', 'amount')
            ->with(['lineItems', 'project'])
            ->orderByRaw('case when status in (?, ?) then 0 else 1 end', [QuoteStatus::Borrador->value, QuoteStatus::Enviada->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Cuántas hay de cada lado, para no tener que cambiar de lista para saber
     * si quedó algo pendiente.
     *
     * @return array{pendientes: int, archivadas: int}
     */
    #[Computed]
    public function quoteCounts(): array
    {
        return [
            'pendientes' => (clone $this->quotesQuery())->whereIn('status', $this->statusValues(QuoteStatus::open()))->count(),
            'archivadas' => (clone $this->quotesQuery())->whereIn('status', $this->statusValues(QuoteStatus::archived()))->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function statusesOfCurrentTab(): array
    {
        return $this->statusValues($this->quotesTab === 'archivadas' ? QuoteStatus::archived() : QuoteStatus::open());
    }

    /**
     * @param  array<int, QuoteStatus>  $statuses
     * @return array<int, string>
     */
    private function statusValues(array $statuses): array
    {
        return array_map(fn (QuoteStatus $status) => $status->value, $statuses);
    }

    /**
     * Hosting, SSL, dominio y correo cuelgan del cliente y no del proyecto,
     * así que ni se ofrecen como categoría de renglón cuando el panel vive en
     * el detalle de un proyecto.
     *
     * @return array<int, ServiceCategory>
     */
    #[Computed]
    public function categoryOptions(): array
    {
        return collect(ServiceCategory::cases())
            ->reject(fn (ServiceCategory $category) => $this->project && $category->belongsToDomain())
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
        Gate::authorize('update', $this->client);

        $this->resetValidation();
        $this->editingQuoteId = $quoteId;

        if ($quoteId === null) {
            $this->quoteName = '';
            $this->quoteDescription = null;
            $this->quoteCurrency = $this->client->currency;
            $this->quoteValidUntil = today()->addDays(30)->toDateString();
            $this->quoteNotes = null;
            $this->quoteIsProject = false;
            $this->lineItems = [$this->blankLineItem()];
        } else {
            $quote = $this->findQuote($quoteId);

            $this->quoteName = $quote->name;
            $this->quoteDescription = $quote->description;
            $this->quoteCurrency = $quote->currency;
            $this->quoteValidUntil = $quote->valid_until?->toDateString();
            $this->quoteNotes = $quote->notes;
            $this->quoteIsProject = $quote->is_project;
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
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'quoteName' => ['required', 'string', 'max:255'],
            'quoteDescription' => ['nullable', 'string', 'max:2000'],
            'quoteCurrency' => ['required', 'string', 'size:3'],
            'quoteValidUntil' => ['nullable', 'date'],
            'quoteNotes' => ['nullable', 'string', 'max:2000'],
            'quoteIsProject' => ['boolean'],
            'lineItems' => ['required', 'array', 'min:1'],
            'lineItems.*.name' => ['required', 'string', 'max:255'],
            'lineItems.*.description' => ['nullable', 'string', 'max:2000'],
            'lineItems.*.category' => [
                'required',
                Rule::enum(ServiceCategory::class),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->project && ServiceCategory::from($value)->belongsToDomain()) {
                        $fail(__('Esta categoría cuelga del cliente, no de un proyecto.'));
                    }
                },
            ],
            'lineItems.*.billing_frequency' => ['required', Rule::enum(ServiceBillingFrequency::class)],
            'lineItems.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $attributes = [
            'name' => $validated['quoteName'],
            'description' => $validated['quoteDescription'],
            'currency' => $validated['quoteCurrency'],
            'valid_until' => $validated['quoteValidUntil'],
            'notes' => $validated['quoteNotes'],
            // Al capturar todavía no vale la pena preguntar: se decide hasta
            // que se edite o se acepte, que es cuando de verdad se sabe si
            // amerita proyecto. Dentro de un proyecto la pregunta tampoco
            // aplica: lo cotizado ya es de ese trabajo.
            'is_project' => $this->editingQuoteId !== null && $this->project === null && $validated['quoteIsProject'],
        ];

        if ($this->editingQuoteId !== null) {
            $quote = $this->findQuote($this->editingQuoteId);
            $quote->update($attributes);
            $this->syncLineItems($quote, $validated['lineItems']);
        } else {
            $quote = $this->client->quotes()->create([
                ...$attributes,
                'project_id' => $this->project?->id,
                'status' => QuoteStatus::Borrador,
            ]);
            $this->syncLineItems($quote, $validated['lineItems']);
        }

        unset($this->quotes, $this->quoteCounts);

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

    public function send(int $quoteId, SendQuote $action): void
    {
        Gate::authorize('update', $this->client);

        $action->handle($this->findQuote($quoteId), auth()->user());

        unset($this->quotes, $this->quoteCounts);

        Flux::toast(variant: 'success', text: __('Cotización marcada como enviada.'));
    }

    /**
     * Dentro de un proyecto la pregunta de si abre proyecto no aplica —lo
     * cotizado ya es de ese trabajo—, así que ahí se acepta directo.
     */
    public function accept(int $quoteId, AcceptQuote $action): void
    {
        Gate::authorize('update', $this->client);

        $this->finishAccepting($this->findQuote($quoteId), $action);
    }

    /**
     * Fuera de un proyecto, aceptar es el momento en que de verdad se sabe si
     * el trabajo amerita abrir uno: se pregunta aquí, no al capturar la
     * cotización.
     */
    public function openAcceptModal(int $quoteId): void
    {
        Gate::authorize('update', $this->client);

        $quote = $this->findQuote($quoteId);

        $this->acceptingQuoteId = $quote->id;
        $this->acceptAsProject = $quote->is_project;

        $this->modal('quote-accept')->show();
    }

    public function confirmAccept(AcceptQuote $action): void
    {
        Gate::authorize('update', $this->client);

        $quote = $this->findQuote($this->acceptingQuoteId ?? 0);
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

        unset($this->quotes, $this->quoteCounts);

        /**
         * Aceptar crea cosas que se pintan fuera de este panel —el proyecto, la
         * línea cobrable, sus cobros—, y esos componentes no se enteran solos:
         * sin el aviso había que recargar la ficha para verlas.
         */
        $this->dispatch('quote-accepted');

        Flux::toast(variant: 'success', text: __('Cotización aceptada: ya existen sus líneas cobrables.'));
    }

    public function openRejectModal(int $quoteId): void
    {
        Gate::authorize('update', $this->client);

        $this->rejectingQuoteId = $this->findQuote($quoteId)->id;
        $this->rejectionReason = null;
        $this->resetValidation();

        $this->modal('quote-rejection')->show();
    }

    public function reject(RejectQuote $action): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'rejectionReason' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->handle($this->findQuote($this->rejectingQuoteId ?? 0), $validated['rejectionReason']);

        unset($this->quotes, $this->quoteCounts);

        $this->rejectingQuoteId = null;

        $this->modal('quote-rejection')->close();

        Flux::toast(variant: 'success', text: __('Cotización marcada como rechazada.'));
    }

    public function closeRejectModal(): void
    {
        $this->rejectingQuoteId = null;

        $this->modal('quote-rejection')->close();
    }

    public function deleteQuote(int $quoteId): void
    {
        Gate::authorize('update', $this->client);

        $quote = $this->findQuote($quoteId);

        if ($quote->lineItems()->whereNotNull('service_id')->exists()) {
            Flux::toast(variant: 'danger', text: __('Esta cotización ya generó una línea cobrable. Bórrala desde ahí si fue un error.'));

            return;
        }

        $quote->delete();

        unset($this->quotes, $this->quoteCounts);

        Flux::toast(variant: 'success', text: __('Cotización eliminada.'));
    }

    /**
     * @return Builder<Quote>
     */
    private function quotesQuery(): Builder
    {
        return Quote::query()
            ->where('client_id', $this->client->id)
            ->when($this->project, fn ($query) => $query->where('project_id', $this->project->id));
    }

    private function findQuote(int $quoteId): Quote
    {
        return $this->quotesQuery()->findOrFail($quoteId);
    }

    public function render(): View
    {
        return view('livewire.quotes-panel');
    }
}
