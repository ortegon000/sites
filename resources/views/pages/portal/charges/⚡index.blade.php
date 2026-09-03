<?php

use App\Enums\ChargeStatus;
use App\Models\Charge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Los cobros del cliente, con proyecto o sin él.
 *
 * Antes solo se veían dentro del detalle de un proyecto, así que a un cliente
 * de puro hosting le cobrábamos cada año sin que tuviera dónde consultarlo.
 * De solo lectura, como todo el portal: aquí se ve lo que se debe, no se paga.
 */
new #[Layout('layouts::portal')] class extends Component {
    public function mount(): void
    {
        abort_if(auth()->user()->contact_id === null, 403);
    }

    /**
     * Lo que sigue debiendo dinero, lo más próximo primero. Un cobro parcial
     * sigue abierto: lo que falta se sigue debiendo.
     *
     * @return Collection<int, Charge>
     */
    #[Computed]
    public function pendingCharges(): Collection
    {
        return $this->chargesQuery()
            ->whereIn('status', ChargeStatus::open())
            ->orderBy('due_date')
            ->get();
    }

    /**
     * @return Collection<int, Charge>
     */
    #[Computed]
    public function paidCharges(): Collection
    {
        return $this->chargesQuery()
            ->where('status', ChargeStatus::Pagado)
            ->orderByDesc('due_date')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function showsCompany(): bool
    {
        return auth()->user()->clients()->count() > 1;
    }

    /**
     * @return Builder<Charge>
     */
    private function chargesQuery(): Builder
    {
        return Charge::query()
            ->whereHas('service', fn (Builder $query) => $query
                ->whereIn('client_id', auth()->user()->clients()->select('clients.id')))
            ->with(['service.client', 'payments']);
    }

    public function render()
    {
        return $this->view()->title(__('Mis cobros'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col">
        <flux:heading size="xl">{{ __('Mis cobros') }}</flux:heading>
        <flux:text class="text-zinc-400">{{ __('Lo que está por pagarse y lo que ya quedó cubierto.') }}</flux:text>
    </div>

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Por pagar') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Concepto') }}</flux:table.column>
                @if ($this->showsCompany)
                    <flux:table.column>{{ __('Empresa') }}</flux:table.column>
                @endif
                <flux:table.column>{{ __('Vencimiento') }}</flux:table.column>
                <flux:table.column>{{ __('Monto') }}</flux:table.column>
                <flux:table.column>{{ __('Restante') }}</flux:table.column>
                <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->pendingCharges as $charge)
                    <flux:table.row wire:key="portal-charge-{{ $charge->id }}">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $charge->conceptLabel() }}</span>
                                @if ($charge->service->project)
                                    <flux:link :href="route('portal.projects.show', $charge->service->project)" wire:navigate class="text-xs text-zinc-400">
                                        {{ $charge->service->project->name }}
                                    </flux:link>
                                @endif
                            </div>
                        </flux:table.cell>
                        @if ($this->showsCompany)
                            <flux:table.cell>{{ $charge->service->client->name }}</flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $charge->due_date?->format('d/m/Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $charge->amount, 2) }} {{ $charge->currency }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($charge->remainingAmount(), 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$charge->status->color()">{{ $charge->status->label() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-400">
                            {{ __('No tienes nada pendiente de pago.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($this->paidCharges->isNotEmpty())
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Pagados') }}</flux:heading>

            <div class="flex flex-col gap-2">
                @foreach ($this->paidCharges as $charge)
                    <div wire:key="portal-charge-paid-{{ $charge->id }}" class="flex items-center justify-between border-b border-zinc-100 pb-2 text-sm last:border-0 dark:border-zinc-700">
                        <div class="flex flex-col">
                            <span>{{ $charge->conceptLabel() }}</span>
                            <span class="text-xs text-zinc-400">
                                {{ $charge->paid_at?->format('d/m/Y') ?? $charge->due_date?->format('d/m/Y') }}
                                @if ($this->showsCompany)
                                    · {{ $charge->service->client->name }}
                                @endif
                            </span>
                        </div>
                        <span>{{ number_format((float) $charge->amount, 2) }} {{ $charge->currency }}</span>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
