{{--
    El formulario de la cotización -nombre, alcance y renglones-, compartido
    por App\Concerns\ManagesQuoteActions entre el panel de la ficha del
    cliente y el listado general de /cotizaciones (ahí solo para editar; ese
    listado nunca crea una desde cero).
--}}
<flux:modal name="quote-form" class="md:w-[48rem] md:max-w-3xl">
    <form wire:submit="saveQuote" class="flex flex-col gap-6">
        <flux:heading size="lg">{{ $editingQuoteId ? __('Editar cotización') : __('Nueva cotización') }}</flux:heading>

        <flux:input wire:model="quoteName" :label="__('Nombre de la propuesta')" required autofocus />
        <flux:textarea wire:model="quoteDescription" :label="__('Alcance general')" rows="2"
            :description="__('Lo que describe el trabajo completo, además de los renglones de abajo.')" />

        <div class="flex flex-col gap-4">
            <flux:heading size="sm">{{ __('Renglones') }}</flux:heading>

            @foreach ($lineItems as $index => $item)
                <div wire:key="line-item-{{ $index }}" class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="flex items-start gap-2">
                        <flux:input wire:model="lineItems.{{ $index }}.name" :label="__('Concepto')" field:class="flex-1" />

                        @if (count($lineItems) > 1)
                            <flux:button size="sm" variant="ghost" icon="trash" class="mt-6"
                                :tooltip="__('Quitar renglón')"
                                wire:click="removeLineItem({{ $index }})"
                                :aria-label="__('Quitar renglón')" />
                        @endif
                    </div>

                    <flux:textarea wire:model="lineItems.{{ $index }}.description" :label="__('Descripción')" rows="2" />

                    <div class="grid grid-cols-3 gap-3">
                        <flux:select wire:model="lineItems.{{ $index }}.category" :label="__('Categoría')">
                            @foreach ($this->categoryOptions as $option)
                                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="lineItems.{{ $index }}.billing_frequency" :label="__('Facturación')">
                            @foreach ($this->frequencyOptions as $option)
                                @if ($option !== \App\Enums\ServiceBillingFrequency::Installment)
                                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                                @endif
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="lineItems.{{ $index }}.amount" type="number" step="0.01" :label="__('Monto')" />
                    </div>

                    @error("lineItems.{$index}.category")
                        <flux:text class="text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
            @endforeach

            <flux:button size="sm" variant="ghost" icon="plus" wire:click="addLineItem">{{ __('Agregar renglón') }}</flux:button>

            <div class="flex justify-end text-sm text-zinc-400">
                {{ __('Total') }}: {{ number_format($this->lineItemsTotal(), 2) }} {{ $quoteCurrency }}
            </div>
        </div>

        <div class="grid grid-cols-2 items-start gap-4">
            <flux:input wire:model="quoteCurrency" :label="__('Moneda')" maxlength="3" />
            <flux:input wire:model="quoteValidUntil" type="date" :label="__('Vigencia')"
                :description:trailing="__('Al pasar esta fecha, una cotización enviada expira sola.')" />
        </div>

        <flux:textarea wire:model="quoteNotes" :label="__('Notas')" rows="2" />

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="closeQuoteModal">{{ __('Cancelar') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
        </div>
    </form>
</flux:modal>
