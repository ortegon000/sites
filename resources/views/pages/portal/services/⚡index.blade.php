<?php

use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Lo que el cliente tiene contratado, tenga proyecto o no.
 *
 * El portal arranca aquí y ya no en los proyectos: la mayoría de los clientes
 * no tiene ninguno abierto —viven de su hosting, su dominio y sus renovaciones—
 * y entraban a "Mis proyectos" para encontrarlo vacío, sin ver nada de lo que
 * sí les cobramos. El proyecto queda como contexto de la línea, no como
 * requisito para que el cliente vea su cuenta.
 */
new #[Layout('layouts::portal')] class extends Component {
    public function mount(): void
    {
        abort_if(auth()->user()->contact_id === null, 403);
    }

    /**
     * Las líneas de todas las empresas de esta persona, activas primero: lo
     * cancelado se queda a la vista como constancia, pero hasta abajo.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()
            ->whereIn('client_id', auth()->user()->clients()->select('clients.id'))
            ->with(['client', 'project'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [ServiceStatus::Activo->value])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function showsCompany(): bool
    {
        return auth()->user()->clients()->count() > 1;
    }

    public function render()
    {
        return $this->view()->title(__('Mis servicios'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col">
        <flux:heading size="xl">{{ __('Mis servicios') }}</flux:heading>
        <flux:text class="text-zinc-400">{{ __('Lo que tienes contratado con nosotros y cada cuándo se cobra.') }}</flux:text>
    </div>

    <flux:card class="flex flex-col gap-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Servicio') }}</flux:table.column>
                @if ($this->showsCompany)
                    <flux:table.column>{{ __('Empresa') }}</flux:table.column>
                @endif
                <flux:table.column>{{ __('Facturación') }}</flux:table.column>
                <flux:table.column>{{ __('Monto') }}</flux:table.column>
                <flux:table.column>{{ __('Próximo cobro') }}</flux:table.column>
                <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->services as $service)
                    <flux:table.row wire:key="portal-service-{{ $service->id }}">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $service->name }}</span>
                                @if ($service->project)
                                    <flux:link :href="route('portal.projects.show', $service->project)" wire:navigate class="text-xs text-zinc-400">
                                        {{ $service->project->name }}
                                    </flux:link>
                                @endif
                            </div>
                        </flux:table.cell>
                        @if ($this->showsCompany)
                            <flux:table.cell>{{ $service->client->name }}</flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $service->billing_frequency->label() }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $service->amount, 2) }} {{ $service->currency }}</flux:table.cell>
                        <flux:table.cell>{{ $service->next_charge_date?->format('d/m/Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $service->status->label() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-400">
                            {{ __('Todavía no tienes servicios contratados.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
