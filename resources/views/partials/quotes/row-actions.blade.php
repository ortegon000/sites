{{--
    Las acciones de una cotización: editar, copiarla, enviar/aceptar/rechazar
    -o deshacer cualquiera de esas tres por si se marcó por error- y
    eliminar. La comparten el panel de la ficha del cliente y el listado
    general de /cotizaciones, vía App\Concerns\ManagesQuoteActions.
--}}
@can('update', $quote->client)
    <div class="flex justify-end gap-2">
        <flux:button size="xs" variant="ghost" icon="pencil"
            :tooltip="__('Editar')"
            wire:click="openQuoteModal({{ $quote->id }})" />

        <flux:button size="xs" variant="ghost" icon="clipboard-document"
            :tooltip="__('Copiar enlace')"
            x-on:click="navigator.clipboard.writeText(@js($this->quotePublicUrl($quote)))"
            wire:click="markCopied({{ $quote->id }})" />

        @if ($quote->status === \App\Enums\QuoteStatus::Borrador)
            <flux:button size="xs" variant="ghost" icon="paper-airplane"
                :tooltip="__('Marcar como enviada')"
                wire:click="send({{ $quote->id }})" />
        @elseif ($quote->status === \App\Enums\QuoteStatus::Enviada)
            <flux:button size="xs" variant="ghost" icon="arrow-uturn-left"
                :tooltip="__('Regresar a borrador')"
                wire:click="undoSend({{ $quote->id }})"
                wire:confirm="{{ __('¿Regresar esta cotización a borrador?') }}" />
        @endif

        @if ($quote->status !== \App\Enums\QuoteStatus::Aceptada)
            @if ($quote->project_id)
                <flux:button size="xs" variant="ghost" icon="check"
                    :tooltip="__('El cliente aceptó')"
                    wire:click="accept({{ $quote->id }})"
                    wire:confirm="{{ __('¿El cliente aceptó? Se creará una línea cobrable por cada renglón.') }}" />
            @else
                <flux:button size="xs" variant="ghost" icon="check"
                    :tooltip="__('El cliente aceptó')"
                    wire:click="openAcceptModal({{ $quote->id }})" />
            @endif
        @else
            <flux:button size="xs" variant="ghost" icon="arrow-uturn-left"
                :tooltip="__('Deshacer aceptación')"
                wire:click="undoAccept({{ $quote->id }})"
                wire:confirm="{{ __('¿Deshacer la aceptación? Se borran las líneas cobrables que generó, si no tienen abonos.') }}" />
        @endif

        @if ($quote->status !== \App\Enums\QuoteStatus::Rechazada && $quote->status !== \App\Enums\QuoteStatus::Aceptada)
            <flux:button size="xs" variant="ghost" icon="x-mark"
                :tooltip="__('El cliente rechazó')"
                wire:click="openRejectModal({{ $quote->id }})" />
        @elseif ($quote->status === \App\Enums\QuoteStatus::Rechazada)
            <flux:button size="xs" variant="ghost" icon="arrow-uturn-left"
                :tooltip="__('Reabrir')"
                wire:click="undoReject({{ $quote->id }})"
                wire:confirm="{{ __('¿Reabrir esta cotización?') }}" />
        @endif

        <flux:button size="xs" variant="ghost" icon="trash"
            :tooltip="__('Eliminar')"
            wire:click="deleteQuote({{ $quote->id }})"
            wire:confirm="{{ __('¿Eliminar esta cotización?') }}" />
    </div>
@endcan
