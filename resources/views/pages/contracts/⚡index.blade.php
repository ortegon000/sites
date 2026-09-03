<?php

use App\Enums\ContractStatus;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Todos los contratos, para poder contestar "qué tenemos firmado y hasta
 * cuándo" sin abrir cliente por cliente.
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
     * @return array<int, ContractStatus>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return ContractStatus::cases();
    }

    #[Computed]
    public function clientOptions()
    {
        return Client::query()->orderBy('name')->get();
    }

    #[Computed]
    public function contracts()
    {
        return Contract::query()
            ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                ->where('contracts.number', 'like', "%{$this->search}%")
                ->orWhere('contracts.title', 'like', "%{$this->search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))))
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->clientFilter, fn ($query) => $query->where('client_id', $this->clientFilter))
            ->with(['client', 'project'])
            ->withCount('services')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * Lo firmado y vigente hoy, que es la pregunta que contesta esta pantalla,
     * separado de lo que está esperando firma.
     */
    #[Computed]
    public function summary(): object
    {
        return (object) [
            'active' => Contract::query()
                ->where('status', ContractStatus::Firmado)
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
                ->count(),
            'awaiting' => Contract::query()->where('status', ContractStatus::Enviado)->count(),
            'expiring' => Contract::query()
                ->where('status', ContractStatus::Firmado)
                ->whereNotNull('ends_on')
                ->whereBetween('ends_on', [today(), today()->addDays(60)])
                ->count(),
        ];
    }

    public function render()
    {
        return $this->view()->title(__('Contratos'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col">
            <flux:heading size="xl">{{ __('Contratos') }}</flux:heading>
            <flux:text class="text-zinc-400">{{ __('Se generan desde la ficha del cliente, con sus servicios y montos.') }}</flux:text>
        </div>

        <div class="flex gap-6 text-sm">
            <div class="flex flex-col">
                <span class="text-zinc-400">{{ __('Vigentes') }}</span>
                <span class="text-lg">{{ $this->summary->active }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-zinc-400">{{ __('Esperando firma') }}</span>
                <span class="text-lg">{{ $this->summary->awaiting }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-zinc-400">{{ __('Terminan en 60 días') }}</span>
                <span class="text-lg">{{ $this->summary->expiring }}</span>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Buscar por folio, título o cliente...')" class="max-w-sm" />

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

    <flux:table :paginate="$this->contracts">
        <flux:table.columns>
            <flux:table.column>{{ __('Folio') }}</flux:table.column>
            <flux:table.column>{{ __('Cliente') }}</flux:table.column>
            <flux:table.column>{{ __('Título') }}</flux:table.column>
            <flux:table.column>{{ __('Vigencia') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->contracts as $contract)
                <flux:table.row wire:key="contract-row-{{ $contract->id }}">
                    <flux:table.cell>{{ $contract->number }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('clients.show', $contract->client)" wire:navigate>{{ $contract->client->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $contract->title }}</span>
                            <span class="text-xs text-zinc-400">
                                {{ trans_choice('{0}Ningún servicio|{1}1 servicio|[2,*]:count servicios', $contract->services_count, ['count' => $contract->services_count]) }}
                                @if ($contract->project)
                                    · {{ $contract->project->name }}
                                @endif
                            </span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $contract->starts_on->format('d/m/Y') }} — {{ $contract->ends_on?->format('d/m/Y') ?? __('indefinida') }}</span>
                            @if ($contract->isExpired())
                                <span class="text-xs text-amber-600 dark:text-amber-500">{{ __('vigencia terminada') }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$contract->status->color()">{{ $contract->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:button size="xs" variant="ghost" icon="printer"
                                :tooltip="__('Versión imprimible')"
                                :href="route('contracts.print', $contract)" target="_blank" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-400">
                        {{ __('Sin contratos todavía.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
