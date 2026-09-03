<?php

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Todo lo cotizado, de un vistazo: qué se ofreció, a quién, por cuánto y quién
 * no ha contestado. Es la lista que antes vivía como filas "Pendiente" sin
 * costo en el archivo del dueño, con el precio escondido en las notas.
 */
new class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?string $statusFilter = null;

    public ?int $clientFilter = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Client::class);
    }

    /**
     * @return array<int, QuoteStatus>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return QuoteStatus::cases();
    }

    #[Computed]
    public function clientOptions()
    {
        return Client::query()->orderBy('name')->get();
    }

    #[Computed]
    public function quotes()
    {
        return $this->filteredQuery()
            ->with(['client', 'project', 'service'])
            ->orderByRaw('case when status in (?, ?) then 0 else 1 end', [QuoteStatus::Borrador->value, QuoteStatus::Enviada->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * Lo que está en juego —lo cotizado y sin contestar— separado de lo que ya
     * se ganó, que es la única forma de saber si vale la pena insistir.
     */
    #[Computed]
    public function summary(): object
    {
        $open = Quote::query()->whereIn('status', QuoteStatus::open());

        return (object) [
            'openCount' => (clone $open)->count(),
            'openAmount' => (float) (clone $open)->sum('amount'),
            'wonAmount' => (float) Quote::query()
                ->where('status', QuoteStatus::Aceptada)
                ->where('decided_at', '>=', today()->subDays(90))
                ->sum('amount'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Quote>
     */
    private function filteredQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Quote::query()
            ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                ->where('quotes.name', 'like', "%{$this->search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))))
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->clientFilter, fn ($query) => $query->where('client_id', $this->clientFilter));
    }

    public function render()
    {
        return $this->view()->title(__('Cotizaciones'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-breadcrumbs :items="[['label' => __('Cotizaciones')]]" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col">
            <flux:heading size="xl">{{ __('Cotizaciones') }}</flux:heading>
            <flux:text class="text-zinc-400">{{ __('Trabajo ofrecido que todavía no es cobro.') }}</flux:text>
        </div>

        <div class="flex gap-6 text-sm">
            <div class="flex flex-col">
                <span class="text-zinc-400">{{ __('Sin contestar') }}</span>
                <span class="text-lg">{{ $this->summary->openCount }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-zinc-400">{{ __('En juego') }}</span>
                <span class="text-lg">{{ number_format($this->summary->openAmount, 2) }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-zinc-400">{{ __('Aceptado (90 días)') }}</span>
                <span class="text-lg">{{ number_format($this->summary->wonAmount, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Buscar por concepto o cliente...')" class="max-w-sm" />

        <flux:select wire:model.live="clientFilter" :placeholder="__('Todos los clientes')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todos los clientes') }}</flux:select.option>
            @foreach ($this->clientOptions as $client)
                <flux:select.option value="{{ $client->id }}">{{ $client->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="statusFilter" :placeholder="__('Todos los estatus')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todos los estatus') }}</flux:select.option>
            @foreach ($this->statusOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->quotes">
        <flux:table.columns>
            <flux:table.column>{{ __('Concepto') }}</flux:table.column>
            <flux:table.column>{{ __('Cliente') }}</flux:table.column>
            <flux:table.column>{{ __('Monto') }}</flux:table.column>
            <flux:table.column>{{ __('Vigencia') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->quotes as $quote)
                <flux:table.row wire:key="quote-row-{{ $quote->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $quote->name }}</span>
                            <span class="text-xs text-zinc-400">
                                {{ $quote->category->label() }} · {{ $quote->billing_frequency->label() }}
                                @if ($quote->project)
                                    · {{ $quote->project->name }}
                                @endif
                            </span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('clients.show', $quote->client)" wire:navigate>{{ $quote->client->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ number_format((float) $quote->amount, 2) }} {{ $quote->currency }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $quote->valid_until?->format('d/m/Y') ?? '—' }}</span>
                            @if ($quote->sent_at)
                                <span class="text-xs text-zinc-400">{{ __('enviada el :date', ['date' => $quote->sent_at->format('d/m/Y')]) }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col gap-1">
                            <flux:badge size="sm" :color="$quote->status->color()">{{ $quote->status->label() }}</flux:badge>
                            @if ($quote->service)
                                <flux:link class="text-xs" :href="route('billables.index')" wire:navigate>{{ __('línea generada') }}</flux:link>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-400">
                        {{ __('Sin cotizaciones. Se capturan desde la ficha del cliente o del prospecto.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
