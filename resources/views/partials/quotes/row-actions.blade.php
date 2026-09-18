{{--
    Las acciones de una cotización: editar, copiarla, enviar/aceptar/rechazar
    -o deshacer cualquiera de esas tres por si se marcó por error- y
    eliminar. La comparten el panel de la ficha del cliente y el listado
    general de /cotizaciones, vía App\Concerns\ManagesQuoteActions.

    Solo el siguiente paso natural queda a la vista (enviar, si es borrador;
    "aceptó", si ya se envió); el resto vive en el menú para que la fila no
    se llene de iconos.
--}}
@can('update', $quote->client)
    @php
        $status = $quote->status;
        $needsAcceptModal = ! $quote->project_id;
    @endphp

    <div class="flex items-center justify-end gap-1">
        @if ($status === \App\Enums\QuoteStatus::Borrador)
            <flux:button size="xs" icon="paper-airplane" wire:click="send({{ $quote->id }})">{{ __('Enviar') }}</flux:button>
        @elseif ($status === \App\Enums\QuoteStatus::Enviada)
            @if ($needsAcceptModal)
                <flux:button size="xs" icon="check" wire:click="openAcceptModal({{ $quote->id }})">{{ __('Aceptó') }}</flux:button>
            @else
                <flux:button size="xs" icon="check"
                    wire:click="accept({{ $quote->id }})"
                    wire:confirm="{{ __('¿El cliente aceptó? Se creará una línea cobrable por cada renglón.') }}">{{ __('Aceptó') }}</flux:button>
            @endif
        @endif

        <flux:dropdown align="end">
            <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Más acciones')" />

            <flux:menu>
                <flux:menu.item icon="pencil" wire:click="openQuoteModal({{ $quote->id }})">{{ __('Editar') }}</flux:menu.item>

                <flux:menu.item icon="clipboard-document"
                    x-on:click="navigator.clipboard.writeText(@js($this->quotePublicUrl($quote)))"
                    wire:click="markCopied({{ $quote->id }})">
                    {{ __('Copiar enlace') }}
                </flux:menu.item>

                @if ($status === \App\Enums\QuoteStatus::Enviada)
                    <flux:menu.item icon="arrow-uturn-left"
                        wire:click="undoSend({{ $quote->id }})"
                        wire:confirm="{{ __('¿Regresar esta cotización a borrador?') }}">
                        {{ __('Regresar a borrador') }}
                    </flux:menu.item>
                @endif

                @if ($status === \App\Enums\QuoteStatus::Aceptada)
                    <flux:menu.item icon="arrow-uturn-left"
                        wire:click="undoAccept({{ $quote->id }})"
                        wire:confirm="{{ __('¿Deshacer la aceptación? Se borran las líneas cobrables que generó, si no tienen abonos.') }}">
                        {{ __('Deshacer aceptación') }}
                    </flux:menu.item>
                @elseif ($status !== \App\Enums\QuoteStatus::Enviada)
                    @if ($needsAcceptModal)
                        <flux:menu.item icon="check" wire:click="openAcceptModal({{ $quote->id }})">{{ __('El cliente aceptó') }}</flux:menu.item>
                    @else
                        <flux:menu.item icon="check"
                            wire:click="accept({{ $quote->id }})"
                            wire:confirm="{{ __('¿El cliente aceptó? Se creará una línea cobrable por cada renglón.') }}">
                            {{ __('El cliente aceptó') }}
                        </flux:menu.item>
                    @endif
                @endif

                @if ($status === \App\Enums\QuoteStatus::Rechazada)
                    <flux:menu.item icon="arrow-uturn-left"
                        wire:click="undoReject({{ $quote->id }})"
                        wire:confirm="{{ __('¿Reabrir esta cotización?') }}">
                        {{ __('Reabrir') }}
                    </flux:menu.item>
                @elseif ($status !== \App\Enums\QuoteStatus::Aceptada)
                    <flux:menu.item icon="x-mark" wire:click="openRejectModal({{ $quote->id }})">{{ __('El cliente rechazó') }}</flux:menu.item>
                @endif

                <flux:menu.separator />

                <flux:menu.item icon="trash" variant="danger"
                    wire:click="deleteQuote({{ $quote->id }})"
                    wire:confirm="{{ __('¿Eliminar esta cotización?') }}">
                    {{ __('Eliminar') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>
@endcan
