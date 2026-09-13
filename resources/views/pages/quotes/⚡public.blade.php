<?php

use App\Actions\Quotes\AcceptQuote;
use App\Actions\Quotes\RejectQuote;
use App\Enums\QuoteStatus;
use App\Models\Quote;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * El enlace que ve el cliente: sin cuenta, protegido solo por lo
 * impredecible del token. Aceptar o rechazar aquí ejecuta las mismas
 * actions que usa el equipo desde el panel, pero sin autorizar contra un
 * usuario -no hay ninguno- y sin la pregunta de "línea suelta o proyecto":
 * esa la decide el equipo después, no el cliente.
 */
new #[Layout('layouts::quote-public')] class extends Component {
    public Quote $quote;

    public ?string $rejectionReason = null;

    public function mount(Quote $quote): void
    {
        $this->quote = $quote->load(['client', 'lineItems']);
    }

    public function accept(AcceptQuote $action): void
    {
        if ($this->quote->status !== QuoteStatus::Enviada) {
            return;
        }

        $action->handle($this->quote);

        $this->quote->refresh();
    }

    public function reject(RejectQuote $action): void
    {
        if ($this->quote->status !== QuoteStatus::Enviada) {
            return;
        }

        $action->handle($this->quote, $this->rejectionReason);

        $this->quote->refresh();
    }

    public function render()
    {
        return $this->view()->title($this->quote->name);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col">
        <flux:heading size="xl">{{ $quote->name }}</flux:heading>
        <flux:text class="text-zinc-400">{{ __('Propuesta para :client', ['client' => $quote->client->name]) }}</flux:text>
    </div>

    @if ($quote->status === \App\Enums\QuoteStatus::Aceptada)
        <flux:callout icon="check-circle" variant="success" :heading="__('¡Gracias! Ya quedó registrada tu aceptación.')" />
    @elseif ($quote->status === \App\Enums\QuoteStatus::Rechazada)
        <flux:callout icon="x-circle" :heading="__('Quedó registrado que no se acepta esta propuesta.')" />
    @elseif ($quote->status !== \App\Enums\QuoteStatus::Enviada)
        <flux:callout icon="exclamation-triangle" variant="warning" :heading="__('Este enlace ya no está disponible.')" />
    @else
        @if ($quote->description)
            <flux:text>{{ $quote->description }}</flux:text>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Concepto') }}</flux:table.column>
                <flux:table.column>{{ __('Facturación') }}</flux:table.column>
                <flux:table.column>{{ __('Monto') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($quote->lineItems as $item)
                    <flux:table.row wire:key="line-item-{{ $item->id }}">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $item->name }}</span>
                                @if ($item->description)
                                    <span class="text-xs text-zinc-400">{{ $item->description }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->billing_frequency->label() }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $item->amount, 2) }} {{ $quote->currency }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="flex flex-col items-end gap-1">
            <flux:heading size="lg">
                {{ __('Total') }}: {{ number_format((float) $quote->lineItems->sum('amount'), 2) }} {{ $quote->currency }}
            </flux:heading>
            @if ($quote->valid_until)
                <flux:text class="text-xs text-zinc-400">
                    {{ __('Vigente hasta el :date', ['date' => $quote->valid_until->format('d/m/Y')]) }}
                </flux:text>
            @endif
        </div>

        <flux:separator />

        <div class="flex flex-col gap-4">
            <flux:button variant="primary" wire:click="accept"
                wire:confirm="{{ __('¿Confirmas que aceptas esta propuesta?') }}">
                {{ __('Aceptar propuesta') }}
            </flux:button>

            <div class="flex flex-col gap-2">
                <flux:textarea wire:model="rejectionReason" :label="__('¿No es lo que buscabas? Cuéntanos por qué (opcional)')" rows="2" />
                <flux:button variant="ghost" wire:click="reject"
                    wire:confirm="{{ __('¿Confirmas que rechazas esta propuesta?') }}">
                    {{ __('Rechazar propuesta') }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
