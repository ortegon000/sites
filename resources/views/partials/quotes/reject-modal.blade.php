{{-- Motivo del rechazo, compartido vía App\Concerns\ManagesQuoteActions. --}}
<flux:modal name="quote-rejection" class="md:w-96" wire:close="closeRejectModal">
    <form wire:submit="reject" class="flex flex-col gap-6">
        <flux:heading size="lg">{{ __('Cotización rechazada') }}</flux:heading>

        <flux:textarea wire:model="rejectionReason" :label="__('Por qué')" rows="3"
            :placeholder="__('Se fue con otro proveedor, lo dejó para el año que entra...')" />

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="closeRejectModal">{{ __('Cancelar') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
        </div>
    </form>
</flux:modal>
