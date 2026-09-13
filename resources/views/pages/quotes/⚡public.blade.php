<?php

use App\Actions\Quotes\AcceptQuote;
use App\Actions\Quotes\RejectQuote;
use App\Enums\QuoteStatus;
use App\Enums\ServiceBillingFrequency;
use App\Models\Quote;
use Livewire\Attributes\Computed;
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

    /**
     * Separado en pago único y recurrente -como en la cláusula de
     * contraprestación del contrato-, porque no es lo mismo un gasto de una
     * vez que uno que se repite.
     */
    #[Computed]
    public function totals(): object
    {
        return (object) [
            'oneTime' => (float) $this->quote->lineItems
                ->where('billing_frequency', ServiceBillingFrequency::OneTime)
                ->sum('amount'),
            'recurring' => (float) $this->quote->lineItems
                ->filter(fn ($item) => $item->billing_frequency->isRecurring())
                ->sum('amount'),
        ];
    }

    public function render()
    {
        return $this->view()->title($this->quote->name);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col">
        <flux:heading size="xl">{{ $quote->name }}</flux:heading>
        <flux:text class="text-zinc-400">{{ __('Propuesta para :client', ['client' => $quote->client->company_name ?? $quote->client->name]) }}</flux:text>
        @if ($quote->sent_at)
            <flux:text class="text-xs text-zinc-400">{{ __('Enviada el :date', ['date' => $quote->sent_at->format('d/m/Y')]) }}</flux:text>
        @endif
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
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $item->billing_frequency->label() }}</span>
                                <span class="text-xs text-zinc-400">{{ $item->category->label() }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $item->amount, 2) }} {{ $quote->currency }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="flex flex-col items-end gap-1">
            @if ($this->totals->oneTime > 0)
                <flux:text>
                    {{ __('Pago único') }}: {{ number_format($this->totals->oneTime, 2) }} {{ $quote->currency }}
                </flux:text>
            @endif
            @if ($this->totals->recurring > 0)
                <flux:text>
                    {{ __('Recurrente') }}: {{ number_format($this->totals->recurring, 2) }} {{ $quote->currency }}
                </flux:text>
            @endif
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

        <div class="flex flex-col gap-2">
            <flux:heading size="sm">{{ __('Términos') }}</flux:heading>
            <ul class="list-disc space-y-1 ps-5 text-xs text-zinc-400">
                <li>{{ __('Los montos están expresados en :currency y no incluyen impuestos.', ['currency' => $quote->currency]) }}</li>
                <li>{{ __('El alcance de esta propuesta es el aquí descrito; cualquier trabajo adicional se cotiza por separado.') }}</li>
                <li>{{ __('Al aceptarse, cada renglón se convierte en una línea de cobro con la periodicidad indicada.') }}</li>
                <li>{{ __('Los dominios, cuentas y licencias que se contraten a tu nombre son de tu propiedad.') }}</li>
                <li>{{ __('Aceptar esta propuesta no sustituye la firma del contrato de prestación de servicios correspondiente.') }}</li>
            </ul>
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
