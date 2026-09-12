<?php

namespace App\Livewire;

use App\Concerns\ManagesQuoteActions;
use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Lo cotizado a un cliente y todavía no aceptado.
 *
 * Vive en la ficha del cliente —y en la del prospecto, que es donde más se
 * usa— porque cotizar pasa antes de que exista proyecto o cobro. Aceptar una
 * cotización es lo que genera, por cada renglón, su línea cobrable.
 *
 * Las acciones sobre una cotización ya existente (editar, enviar, aceptar,
 * rechazar y deshacer cualquiera de esas tres) viven en
 * `App\Concerns\ManagesQuoteActions`, compartidas con el listado general de
 * `/cotizaciones`.
 */
class QuotesPanel extends Component
{
    use ManagesQuoteActions;

    public Client $client;

    public ?Project $project = null;

    /**
     * Quien acaba de resolver o crear el cliente desde /cotizaciones llega
     * aquí con esta bandera para no tener que volver a buscar el botón.
     */
    #[Url(as: 'nueva_cotizacion', except: false)]
    public bool $nuevaCotizacion = false;

    /**
     * Qué lista se está viendo: lo que sigue esperando respuesta o lo ya
     * decidido. Abre en pendientes, que es sobre lo que se actúa.
     */
    public string $quotesTab = 'pendientes';

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

    protected function findQuoteForAction(int $quoteId): Quote
    {
        return $this->quotesQuery()->findOrFail($quoteId);
    }

    protected function refreshQuoteLists(): void
    {
        unset($this->quotes, $this->quoteCounts);
    }

    protected function newQuoteClient(): Client
    {
        return $this->client;
    }

    protected function newQuoteProjectId(): ?int
    {
        return $this->project?->id;
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

    public function render(): View
    {
        return view('livewire.quotes-panel');
    }
}
