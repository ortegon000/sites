<?php

use App\Actions\Renewals\MarkRenewalNotRenewed;
use App\Actions\Renewals\MarkRenewalRenewed;
use App\Actions\Renewals\NotifyClientOfRenewal;
use App\Actions\Renewals\OpenRenewalCycles;
use App\Enums\RenewalStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\License;
use App\Models\Renewal;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Qué caduca en los próximos N días, todo junto: dominios, licencias y
 * servicios anuales. Es el Excel de renovaciones, con el ciclo explícito
 * —por avisar, avisado, renovó, no renovó— que antes no tenía dónde vivir.
 */
new class extends Component {
    #[Url]
    public int $windowDays = 60;

    public ?string $statusFilter = null;

    public ?string $kindFilter = null;

    public ?int $clientFilter = null;

    public ?int $editingRenewalId = null;

    public ?string $renewalAmount = null;

    public ?string $renewalNotes = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Client::class);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function kindOptions(): array
    {
        return [
            Domain::class => __('Dominios'),
            License::class => __('Licencias'),
            Service::class => __('Servicios anuales'),
        ];
    }

    /**
     * @return array<int, RenewalStatus>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return RenewalStatus::cases();
    }

    #[Computed]
    public function clientOptions()
    {
        return Client::query()->orderBy('name')->get();
    }

    #[Computed]
    public function renewals()
    {
        return Renewal::query()
            ->whereDate('due_date', '<=', today()->addDays($this->windowDays))
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->kindFilter, fn ($query) => $query->where('renewable_type', $this->kindFilter))
            ->when($this->clientFilter, fn ($query) => $query->where('client_id', $this->clientFilter))
            ->when(! $this->statusFilter, fn ($query) => $query->whereIn('status', RenewalStatus::open()))
            ->with(['client', 'renewable', 'service'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Lo que hay que perseguir hoy, separado de lo que solo está en el radar.
     */
    #[Computed]
    public function summary(): object
    {
        $open = Renewal::query()->whereIn('status', RenewalStatus::open());

        return (object) [
            'toNotify' => (clone $open)->where('status', RenewalStatus::PorAvisar)->whereDate('due_date', '<=', today()->addDays(30))->count(),
            'waiting' => (clone $open)->where('status', RenewalStatus::Avisado)->count(),
            'overdue' => (clone $open)->whereDate('due_date', '<', today())->count(),
        ];
    }

    /**
     * Abrir ciclos a mano sirve para cuando alguien acaba de capturar la fecha
     * de renovación que faltaba y no quiere esperar a la corrida diaria.
     */
    public function refreshCycles(OpenRenewalCycles $action): void
    {
        Gate::authorize('viewAny', Client::class);

        $opened = $action->handle();

        unset($this->renewals, $this->summary);

        Flux::toast(variant: 'success', text: trans_choice('{0}Sin ciclos nuevos.|{1}Se abrió 1 ciclo nuevo.|[2,*]Se abrieron :count ciclos nuevos.', $opened, ['count' => $opened]));
    }

    public function notifyClient(int $renewalId, NotifyClientOfRenewal $action): void
    {
        $renewal = $this->findRenewal($renewalId);

        Gate::authorize('update', $renewal->client);

        if (! $action->handle($renewal)) {
            Flux::toast(variant: 'danger', text: __('Este cliente no tiene ningún contacto con correo. Agrega uno en su ficha.'));

            return;
        }

        unset($this->renewals, $this->summary);

        Flux::toast(variant: 'success', text: __('Aviso enviado al cliente.'));
    }

    public function openAmountModal(int $renewalId): void
    {
        $renewal = $this->findRenewal($renewalId);

        Gate::authorize('update', $renewal->client);

        $this->editingRenewalId = $renewal->id;
        $this->renewalAmount = $renewal->amount;
        $this->renewalNotes = $renewal->notes;
        $this->resetValidation();

        $this->modal('renewal-form')->show();
    }

    public function saveRenewal(): void
    {
        $renewal = $this->findRenewal($this->editingRenewalId ?? 0);

        Gate::authorize('update', $renewal->client);

        $validated = $this->validate([
            'renewalAmount' => ['nullable', 'numeric', 'min:0'],
            'renewalNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $renewal->update([
            'amount' => $validated['renewalAmount'],
            'notes' => $validated['renewalNotes'],
        ]);

        unset($this->renewals);

        $this->modal('renewal-form')->close();

        Flux::toast(variant: 'success', text: __('Renovación actualizada.'));
    }

    public function closeAmountModal(): void
    {
        $this->modal('renewal-form')->close();
    }

    public function markRenewed(int $renewalId, MarkRenewalRenewed $action): void
    {
        $renewal = $this->findRenewal($renewalId);

        Gate::authorize('update', $renewal->client);

        $action->handle($renewal);

        unset($this->renewals, $this->summary);

        Flux::toast(variant: 'success', text: __('Renovación registrada.'));
    }

    public function markNotRenewed(int $renewalId, MarkRenewalNotRenewed $action): void
    {
        $renewal = $this->findRenewal($renewalId);

        Gate::authorize('update', $renewal->client);

        $action->handle($renewal);

        unset($this->renewals, $this->summary);

        Flux::toast(variant: 'success', text: __('Baja registrada.'));
    }

    private function findRenewal(int $renewalId): Renewal
    {
        return Renewal::with(['client', 'renewable'])->findOrFail($renewalId);
    }

    public function render()
    {
        return $this->view()->title(__('Renovaciones'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-breadcrumbs :items="[['label' => __('Renovaciones')]]" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col">
            <flux:heading size="xl">{{ __('Renovaciones') }}</flux:heading>
            <flux:text class="text-zinc-400">{{ __('Dominios, licencias y servicios anuales que caducan pronto.') }}</flux:text>
        </div>

        <flux:button size="sm" icon="arrow-path" wire:click="refreshCycles">{{ __('Buscar caducidades') }}</flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card class="flex flex-col gap-1">
            <flux:text class="text-zinc-400">{{ __('Por avisar (30 días)') }}</flux:text>
            <flux:heading size="lg">{{ $this->summary->toNotify }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text class="text-zinc-400">{{ __('Esperando respuesta') }}</flux:text>
            <flux:heading size="lg">{{ $this->summary->waiting }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text class="text-zinc-400">{{ __('Ya vencidas sin decisión') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->summary->overdue > 0 ? 'text-red-500' : '' }}">{{ $this->summary->overdue }}</flux:heading>
        </flux:card>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <flux:select wire:model.live="windowDays" class="max-w-xs">
            <flux:select.option value="30">{{ __('Próximos 30 días') }}</flux:select.option>
            <flux:select.option value="60">{{ __('Próximos 60 días') }}</flux:select.option>
            <flux:select.option value="90">{{ __('Próximos 90 días') }}</flux:select.option>
            <flux:select.option value="365">{{ __('Próximo año') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="kindFilter" :placeholder="__('Todo lo que caduca')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todo lo que caduca') }}</flux:select.option>
            @foreach ($this->kindOptions as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="statusFilter" :placeholder="__('Ciclos abiertos')" class="max-w-xs">
            <flux:select.option value="">{{ __('Ciclos abiertos') }}</flux:select.option>
            @foreach ($this->statusOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="clientFilter" :placeholder="__('Todos los clientes')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todos los clientes') }}</flux:select.option>
            @foreach ($this->clientOptions as $client)
                <flux:select.option value="{{ $client->id }}">{{ $client->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Qué caduca') }}</flux:table.column>
            <flux:table.column>{{ __('Cliente') }}</flux:table.column>
            <flux:table.column>{{ __('Vence') }}</flux:table.column>
            <flux:table.column>{{ __('Costo') }}</flux:table.column>
            <flux:table.column>{{ __('Ciclo') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->renewals as $renewal)
                <flux:table.row wire:key="renewal-{{ $renewal->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $renewal->subject() }}</span>
                            <span class="text-xs text-zinc-400">
                                {{ $renewal->kindLabel() }}
                                @if ($renewal->notes)
                                    · {{ $renewal->notes }}
                                @endif
                            </span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('clients.show', $renewal->client)" wire:navigate>{{ $renewal->client->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $renewal->due_date->format('d/m/Y') }}</span>
                            <span class="text-xs {{ $renewal->daysLeft() < 0 ? 'text-red-500' : 'text-zinc-400' }}">
                                {{ $renewal->daysLeft() < 0
                                    ? __('venció hace :days días', ['days' => abs($renewal->daysLeft())])
                                    : __('en :days días', ['days' => $renewal->daysLeft()]) }}
                            </span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $renewal->amount !== null ? number_format((float) $renewal->amount, 2).' '.$renewal->currency : '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col gap-1">
                            <flux:badge size="sm" :color="$renewal->status->color()">{{ $renewal->status->label() }}</flux:badge>
                            @if ($renewal->notified_at)
                                <span class="text-xs text-zinc-400">{{ __('avisado el :date', ['date' => $renewal->notified_at->format('d/m/Y')]) }}</span>
                            @endif
                            @if ($renewal->service)
                                <flux:link class="text-xs" :href="route('billables.index')" wire:navigate>{{ __('línea generada') }}</flux:link>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="xs" variant="ghost" icon="pencil"
                                :tooltip="__('Costo y notas')"
                                wire:click="openAmountModal({{ $renewal->id }})" />

                            @if ($renewal->isOpen())
                                <flux:button size="xs" variant="ghost" icon="envelope"
                                    :tooltip="__('Avisar al cliente')"
                                    wire:click="notifyClient({{ $renewal->id }})" />

                                <flux:button size="xs" variant="ghost" icon="check"
                                    :tooltip="__('Renovó')"
                                    wire:click="markRenewed({{ $renewal->id }})"
                                    wire:confirm="{{ __('¿Registrar que renovó? Se empuja la fecha un año y se genera la línea cobrable.') }}" />

                                <flux:button size="xs" variant="ghost" icon="no-symbol"
                                    :tooltip="__('No renovó')"
                                    wire:click="markNotRenewed({{ $renewal->id }})"
                                    wire:confirm="{{ __('¿Registrar que no renovó? Se dará de baja.') }}" />
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-400">
                        {{ __('Nada por renovar en esta ventana. Si esperabas algo aquí, revisa que el dominio o la licencia tenga capturada su fecha.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="renewal-form" class="md:w-96">
        <form wire:submit="saveRenewal" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Costo de la renovación') }}</flux:heading>

            <flux:input wire:model="renewalAmount" type="number" step="0.01" :label="__('Costo')"
                :description="__('Es lo que se cobrará al renovar, y lo que ve el cliente en su aviso.')" />

            <flux:textarea wire:model="renewalNotes" :label="__('Notas')" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeAmountModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
