<?php

use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Todo lo cobrable en una sola lista: servicios recurrentes y trabajos, con o
 * sin proyecto. Sustituye al menú de Proyectos, que no era ni lo más importante
 * ni lo más frecuente —de unas setenta líneas al año, las que son proyectos de
 * verdad son cinco o seis—, y deja al proyecto como una columna más.
 */
new class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?string $statusFilter = null;

    public ?string $categoryFilter = null;

    public ?string $frequencyFilter = null;

    public ?int $clientFilter = null;

    public ?int $agencyFilter = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Client::class);
    }

    /**
     * @return array<int, ServiceStatus>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return ServiceStatus::cases();
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

    #[Computed]
    public function clientOptions()
    {
        return Client::query()->orderBy('name')->get();
    }

    #[Computed]
    public function agencyOptions()
    {
        return Agency::query()->orderBy('name')->get();
    }

    #[Computed]
    public function services()
    {
        return $this->filteredQuery()
            ->with(['client', 'project'])
            ->withSum('charges as billed_total', 'amount')
            ->withSum('payments as collected_total', 'charge_payments.amount')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * Los totales son de todo lo filtrado, no solo de la página a la vista: la
     * pregunta que contesta esta pantalla es "cuánto hay aquí", y paginar la
     * respuesta la volvería inútil.
     */
    #[Computed]
    public function totals(): object
    {
        return (object) [
            'count' => $this->filteredQuery()->count(),
            'billed' => (float) $this->filteredQuery()->join('charges', 'charges.service_id', '=', 'services.id')->sum('charges.amount'),
            'collected' => (float) $this->filteredQuery()
                ->join('charges', 'charges.service_id', '=', 'services.id')
                ->join('charge_payments', 'charge_payments.charge_id', '=', 'charges.id')
                ->sum('charge_payments.amount'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Service>
     */
    private function filteredQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Service::query()
            ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                ->where('services.name', 'like', "%{$this->search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))))
            ->when($this->statusFilter, fn ($query) => $query->where('services.status', $this->statusFilter))
            ->when($this->categoryFilter, fn ($query) => $query->where('services.category', $this->categoryFilter))
            ->when($this->frequencyFilter, fn ($query) => $query->where('services.billing_frequency', $this->frequencyFilter))
            ->when($this->clientFilter, fn ($query) => $query->where('services.client_id', $this->clientFilter))
            ->when($this->agencyFilter, fn ($query) => $query->whereHas('client', fn ($c) => $c->where('agency_id', $this->agencyFilter)));
    }

    public function render()
    {
        return $this->view()->title(__('Trabajos y cobros'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-breadcrumbs :items="[['label' => __('Trabajos y cobros')]]" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col">
            <flux:heading size="xl">{{ __('Trabajos y cobros') }}</flux:heading>
            <flux:text class="text-zinc-400">{{ __('Todo lo cobrable, con o sin proyecto.') }}</flux:text>
        </div>

        <div class="flex gap-6 text-sm">
            <div class="flex flex-col">
                <span class="text-zinc-400">{{ __('Líneas') }}</span>
                <span class="text-lg">{{ $this->totals->count }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-zinc-400">{{ __('Facturado') }}</span>
                <span class="text-lg">{{ number_format($this->totals->billed, 2) }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-zinc-400">{{ __('Cobrado') }}</span>
                <span class="text-lg">{{ number_format($this->totals->collected, 2) }}</span>
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

        <flux:select wire:model.live="agencyFilter" :placeholder="__('Todas las agencias')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todas las agencias') }}</flux:select.option>
            @foreach ($this->agencyOptions as $agency)
                <flux:select.option value="{{ $agency->id }}">{{ $agency->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="categoryFilter" :placeholder="__('Todas las categorías')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todas las categorías') }}</flux:select.option>
            @foreach ($this->categoryOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="frequencyFilter" :placeholder="__('Toda la facturación')" class="max-w-xs">
            <flux:select.option value="">{{ __('Toda la facturación') }}</flux:select.option>
            @foreach ($this->frequencyOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="statusFilter" :placeholder="__('Todos los estatus')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todos los estatus') }}</flux:select.option>
            @foreach ($this->statusOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->services">
        <flux:table.columns>
            <flux:table.column>{{ __('Concepto') }}</flux:table.column>
            <flux:table.column>{{ __('Cliente') }}</flux:table.column>
            <flux:table.column>{{ __('Proyecto') }}</flux:table.column>
            <flux:table.column>{{ __('Facturación') }}</flux:table.column>
            <flux:table.column>{{ __('Monto') }}</flux:table.column>
            <flux:table.column>{{ __('Cobrado / por cobrar') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->services as $service)
                <flux:table.row wire:key="billable-{{ $service->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $service->name }}</span>
                            <span class="text-xs text-zinc-400">{{ $service->category->label() }} · {{ $service->starts_on->format('d/m/Y') }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('clients.show', $service->client)" wire:navigate>{{ $service->client->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($service->project)
                            <flux:link :href="route('projects.show', $service->project)" wire:navigate>{{ $service->project->name }}</flux:link>
                        @else
                            <span class="text-zinc-400">{{ __('Línea suelta') }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $service->billing_frequency->label() }}</flux:table.cell>
                    <flux:table.cell>{{ number_format((float) $service->amount, 2) }} {{ $service->currency }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            @php ($pending = max(0, (float) ($service->billed_total ?? 0) - (float) ($service->collected_total ?? 0)))
                            <span>{{ number_format((float) ($service->collected_total ?? 0), 2) }}</span>
                            <span class="text-xs {{ $pending > 0 ? 'text-amber-600 dark:text-amber-500' : 'text-zinc-400' }}">
                                {{ __('por cobrar :amount', ['amount' => number_format($pending, 2)]) }}
                            </span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $service->status->label() }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-400">
                        {{ __('Sin resultados.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
