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
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Lo cotizado a un cliente y todavía no aceptado.
 *
 * Vive en la ficha del cliente —y en la del prospecto, que es donde más se
 * usa— porque cotizar pasa antes de que exista proyecto o cobro. Aceptar una
 * cotización es lo que genera la línea cobrable.
 */
class QuotesPanel extends Component
{
    public Client $client;

    public ?Project $project = null;

    public ?int $editingQuoteId = null;

    public string $quoteName = '';

    public ?string $quoteDescription = null;

    public string $quoteCategory = ServiceCategory::Other->value;

    public string $quoteFrequency = ServiceBillingFrequency::OneTime->value;

    public string $quoteAmount = '';

    public string $quoteCurrency = 'MXN';

    public ?string $quoteValidUntil = null;

    public ?string $quoteNotes = null;

    /**
     * Marcado en el formulario: al aceptarse, lo cotizado abre proyecto en vez
     * de quedar como línea suelta del cliente.
     */
    public bool $quoteIsProject = false;

    /**
     * Qué lista se está viendo: lo que sigue esperando respuesta o lo ya
     * decidido. Abre en pendientes, que es sobre lo que se actúa.
     */
    public string $quotesTab = 'pendientes';

    public ?int $rejectingQuoteId = null;

    public ?string $rejectionReason = null;

    public function mount(Client $client, ?Project $project = null): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
        $this->project = $project;
        $this->quoteCurrency = $client->currency;
    }

    /**
     * @return Collection<int, Quote>
     */
    #[Computed]
    public function quotes(): Collection
    {
        return $this->quotesQuery()
            ->whereIn('status', $this->statusesOfCurrentTab())
            ->with(['service', 'project'])
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
     * @return array<int, ServiceCategory>
     */
    #[Computed]
    public function categoryOptions(): array
    {
        return ServiceCategory::cases();
    }

    /**
     * @return array<int, ServiceBillingFrequency>
     */
    #[Computed]
    public function frequencyOptions(): array
    {
        return ServiceBillingFrequency::cases();
    }

    public function openQuoteModal(?int $quoteId = null): void
    {
        Gate::authorize('update', $this->client);

        $this->resetValidation();
        $this->editingQuoteId = $quoteId;

        if ($quoteId === null) {
            $this->quoteName = '';
            $this->quoteDescription = null;
            $this->quoteCategory = ServiceCategory::Other->value;
            $this->quoteFrequency = ServiceBillingFrequency::OneTime->value;
            $this->quoteAmount = '';
            $this->quoteCurrency = $this->client->currency;
            $this->quoteValidUntil = today()->addDays(30)->toDateString();
            $this->quoteNotes = null;
            $this->quoteIsProject = false;
        } else {
            $quote = $this->findQuote($quoteId);

            $this->quoteName = $quote->name;
            $this->quoteDescription = $quote->description;
            $this->quoteCategory = $quote->category->value;
            $this->quoteFrequency = $quote->billing_frequency->value;
            $this->quoteAmount = $quote->amount;
            $this->quoteCurrency = $quote->currency;
            $this->quoteValidUntil = $quote->valid_until?->toDateString();
            $this->quoteNotes = $quote->notes;
            $this->quoteIsProject = $quote->is_project;
        }

        $this->modal('quote-form')->show();
    }

    public function saveQuote(): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'quoteName' => ['required', 'string', 'max:255'],
            'quoteDescription' => ['nullable', 'string', 'max:2000'],
            'quoteCategory' => ['required', Rule::enum(ServiceCategory::class)],
            'quoteFrequency' => ['required', Rule::enum(ServiceBillingFrequency::class)],
            'quoteAmount' => ['required', 'numeric', 'min:0'],
            'quoteCurrency' => ['required', 'string', 'size:3'],
            'quoteValidUntil' => ['nullable', 'date'],
            'quoteNotes' => ['nullable', 'string', 'max:2000'],
            'quoteIsProject' => ['boolean'],
        ]);

        $attributes = [
            'name' => $validated['quoteName'],
            'description' => $validated['quoteDescription'],
            'category' => ServiceCategory::from($validated['quoteCategory']),
            'billing_frequency' => ServiceBillingFrequency::from($validated['quoteFrequency']),
            'amount' => $validated['quoteAmount'],
            'currency' => $validated['quoteCurrency'],
            'valid_until' => $validated['quoteValidUntil'],
            'notes' => $validated['quoteNotes'],
            // Dentro de un proyecto la pregunta no aplica: lo cotizado ya es de ese trabajo.
            'is_project' => $this->project === null && $validated['quoteIsProject'],
        ];

        if ($this->editingQuoteId !== null) {
            $this->findQuote($this->editingQuoteId)->update($attributes);
        } else {
            $this->client->quotes()->create([
                ...$attributes,
                'project_id' => $this->project?->id,
                'status' => QuoteStatus::Borrador,
            ]);
        }

        unset($this->quotes, $this->quoteCounts);

        $this->modal('quote-form')->close();

        Flux::toast(variant: 'success', text: __('Cotización guardada.'));
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

    public function accept(int $quoteId, AcceptQuote $action): void
    {
        Gate::authorize('update', $this->client);

        $action->handle($this->findQuote($quoteId), auth()->user());

        unset($this->quotes, $this->quoteCounts);

        /**
         * Aceptar crea cosas que se pintan fuera de este panel —el proyecto, la
         * línea cobrable, sus cobros—, y esos componentes no se enteran solos:
         * sin el aviso había que recargar la ficha para verlas.
         */
        $this->dispatch('quote-accepted');

        Flux::toast(variant: 'success', text: __('Cotización aceptada: ya existe su línea cobrable.'));
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

        if ($quote->service_id !== null) {
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
