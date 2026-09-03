<?php

use App\Enums\RenewalStatus;
use App\Models\Renewal;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Lo que el cliente ve cuando llega desde el correo de aviso: qué se le
 * renueva, cuándo y cuánto cuesta. De solo lectura, como todo el portal, y sin
 * una sola credencial: lo que se decide aquí se decide contestando el correo.
 */
new #[Layout('layouts::portal')] class extends Component {
    public function mount(): void
    {
        abort_if(auth()->user()->contact_id === null, 403);
    }

    /**
     * @return Collection<int, Renewal>
     */
    #[Computed]
    public function renewals(): Collection
    {
        return Renewal::query()
            ->whereIn('client_id', auth()->user()->clients()->select('clients.id'))
            ->whereIn('status', RenewalStatus::open())
            ->with(['client', 'renewable'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * @return Collection<int, Renewal>
     */
    #[Computed]
    public function history(): Collection
    {
        return Renewal::query()
            ->whereIn('client_id', auth()->user()->clients()->select('clients.id'))
            ->whereNotIn('status', RenewalStatus::open())
            ->with(['client', 'renewable'])
            ->orderByDesc('due_date')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return $this->view()->title(__('Mis renovaciones'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col">
        <flux:heading size="xl">{{ __('Mis renovaciones') }}</flux:heading>
        <flux:text class="text-zinc-400">{{ __('Lo que está por renovarse. Si algo no quieres renovarlo, contéstanos el correo del aviso.') }}</flux:text>
    </div>

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Próximas') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Qué se renueva') }}</flux:table.column>
                <flux:table.column>{{ __('Empresa') }}</flux:table.column>
                <flux:table.column>{{ __('Fecha') }}</flux:table.column>
                <flux:table.column>{{ __('Costo') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->renewals as $renewal)
                    <flux:table.row wire:key="portal-renewal-{{ $renewal->id }}">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $renewal->subject() }}</span>
                                <span class="text-xs text-zinc-400">{{ $renewal->kindLabel() }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $renewal->client->name }}</flux:table.cell>
                        <flux:table.cell>{{ $renewal->due_date->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $renewal->amount !== null ? number_format((float) $renewal->amount, 2).' '.$renewal->currency : __('Por confirmar') }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-400">
                            {{ __('No tienes nada por renovar ahora mismo.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($this->history->isNotEmpty())
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Historial') }}</flux:heading>

            <div class="flex flex-col gap-2">
                @foreach ($this->history as $renewal)
                    <div wire:key="portal-renewal-history-{{ $renewal->id }}" class="flex items-center justify-between border-b border-zinc-100 pb-2 text-sm last:border-0 dark:border-zinc-700">
                        <div class="flex flex-col">
                            <span>{{ $renewal->subject() }}</span>
                            <span class="text-xs text-zinc-400">{{ $renewal->kindLabel() }} · {{ $renewal->due_date->format('d/m/Y') }}</span>
                        </div>
                        <flux:badge size="sm" :color="$renewal->status->color()">{{ $renewal->status->label() }}</flux:badge>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
