{{-- Confirmación al aceptar, compartida vía App\Concerns\ManagesQuoteActions. --}}
<flux:modal name="quote-accept" class="md:w-96" wire:close="closeAcceptModal">
    <form wire:submit="confirmAccept" class="flex flex-col gap-6">
        <flux:heading size="lg">{{ __('El cliente aceptó') }}</flux:heading>
        <flux:text class="text-zinc-400">{{ __('Se creará una línea cobrable por cada renglón.') }}</flux:text>

        <flux:radio.group wire:model="acceptAsProject" variant="segmented"
            :label="__('¿Cómo entra el trabajo?')"
            :description="__('Proyecto abre un expediente nuevo y las líneas cobrables de sus renglones nacen dentro. Línea suelta las deja colgando del cliente directamente. Los de hosting, SSL, dominio y correo siempre cuelgan del cliente, aunque se elija proyecto.')">
            <flux:radio value="0">{{ __('Línea suelta') }}</flux:radio>
            <flux:radio value="1">{{ __('Proyecto') }}</flux:radio>
        </flux:radio.group>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="closeAcceptModal">{{ __('Cancelar') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Aceptar cotización') }}</flux:button>
        </div>
    </form>
</flux:modal>
