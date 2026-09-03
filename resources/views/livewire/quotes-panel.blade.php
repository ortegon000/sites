<flux:card class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-col">
            <flux:heading size="lg">{{ __('Cotizaciones') }}</flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ __('Trabajo ofrecido y todavía sin aceptar. Aceptarla genera su línea cobrable.') }}</flux:text>
        </div>

        @can('update', $client)
            <flux:button size="sm" icon="plus" wire:click="openQuoteModal">{{ __('Nueva cotización') }}</flux:button>
        @endcan
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Concepto') }}</flux:table.column>
            <flux:table.column>{{ __('Monto') }}</flux:table.column>
            <flux:table.column>{{ __('Vigencia') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->quotes as $quote)
                <flux:table.row wire:key="quote-{{ $quote->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $quote->name }}</span>
                            <span class="text-xs text-zinc-400">
                                {{ $quote->category->label() }} · {{ $quote->billing_frequency->label() }}
                                @if (! $project && $quote->project)
                                    · {{ $quote->project->name }}
                                @endif
                            </span>
                            @if ($quote->notes)
                                <span class="text-xs text-zinc-400">{{ $quote->notes }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ number_format((float) $quote->amount, 2) }} {{ $quote->currency }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $quote->valid_until?->format('d/m/Y') ?? '—' }}</span>
                            @if ($quote->sent_at)
                                <span class="text-xs text-zinc-400">{{ __('enviada el :date', ['date' => $quote->sent_at->format('d/m/Y')]) }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col gap-1">
                            <flux:badge size="sm" :color="$quote->status->color()">{{ $quote->status->label() }}</flux:badge>
                            @if ($quote->service)
                                <span class="text-xs text-zinc-400">{{ __('línea generada') }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('update', $client)
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" variant="ghost" icon="pencil"
                                    :tooltip="__('Editar')"
                                    wire:click="openQuoteModal({{ $quote->id }})" />

                                @if ($quote->status === \App\Enums\QuoteStatus::Borrador)
                                    <flux:button size="xs" variant="ghost" icon="paper-airplane"
                                        :tooltip="__('Marcar como enviada')"
                                        wire:click="send({{ $quote->id }})" />
                                @endif

                                @if ($quote->status !== \App\Enums\QuoteStatus::Aceptada)
                                    <flux:button size="xs" variant="ghost" icon="check"
                                        :tooltip="__('El cliente aceptó')"
                                        wire:click="accept({{ $quote->id }})"
                                        wire:confirm="{{ __('¿El cliente aceptó? Se creará la línea cobrable con este monto.') }}" />
                                @endif

                                @if ($quote->status !== \App\Enums\QuoteStatus::Rechazada && $quote->status !== \App\Enums\QuoteStatus::Aceptada)
                                    <flux:button size="xs" variant="ghost" icon="x-mark"
                                        :tooltip="__('El cliente rechazó')"
                                        wire:click="openRejectModal({{ $quote->id }})" />
                                @endif

                                <flux:button size="xs" variant="ghost" icon="trash"
                                    :tooltip="__('Eliminar')"
                                    wire:click="deleteQuote({{ $quote->id }})"
                                    wire:confirm="{{ __('¿Eliminar esta cotización?') }}" />
                            </div>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-400">
                        {{ __('Sin cotizaciones. Aquí vive lo que ya ofreciste y todavía no te contestan.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="quote-form" class="md:w-96">
        <form wire:submit="saveQuote" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ $editingQuoteId ? __('Editar cotización') : __('Nueva cotización') }}</flux:heading>

            <flux:input wire:model="quoteName" :label="__('Concepto')" required autofocus />
            <flux:textarea wire:model="quoteDescription" :label="__('Descripción')" rows="2" />

            <flux:select wire:model="quoteCategory" :label="__('Categoría')">
                @foreach ($this->categoryOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="quoteFrequency" :label="__('Facturación al aceptarse')">
                @foreach ($this->frequencyOptions as $option)
                    @if ($option !== \App\Enums\ServiceBillingFrequency::Installment)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endif
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="quoteAmount" type="number" step="0.01" :label="__('Monto')" />
                <flux:input wire:model="quoteCurrency" :label="__('Moneda')" maxlength="3" />
            </div>

            <flux:input wire:model="quoteValidUntil" type="date" :label="__('Vigencia')"
                :description="__('Al pasar esta fecha, una cotización enviada expira sola.')" />

            <flux:textarea wire:model="quoteNotes" :label="__('Notas')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeQuoteModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>

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
</flux:card>
