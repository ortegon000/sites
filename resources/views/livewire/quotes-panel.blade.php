<flux:card class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-col">
            <flux:heading size="lg">{{ __('Cotizaciones') }}</flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ __('Trabajo ofrecido y todavía sin aceptar. Aceptarla genera, por cada renglón, su línea cobrable.') }}</flux:text>
        </div>

        @can('update', $client)
            <flux:button size="sm" icon="plus" wire:click="openQuoteModal">{{ __('Nueva cotización') }}</flux:button>
        @endcan
    </div>

    <flux:radio.group wire:model.live="quotesTab" variant="segmented" size="sm" class="self-start">
        <flux:radio value="pendientes">{{ __('Pendientes (:count)', ['count' => $this->quoteCounts['pendientes']]) }}</flux:radio>
        <flux:radio value="archivadas">{{ __('Archivadas (:count)', ['count' => $this->quoteCounts['archivadas']]) }}</flux:radio>
    </flux:radio.group>

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
                                {{ $quote->lineItems->pluck('name')->implode(' · ') }}
                                @if (! $project && $quote->project)
                                    · {{ $quote->project->name }}
                                @elseif ($quote->is_project)
                                    · {{ __('abre proyecto') }}
                                @endif
                            </span>
                            @if ($quote->notes)
                                <span class="text-xs text-zinc-400">{{ $quote->notes }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ number_format((float) $quote->amount_total, 2) }} {{ $quote->currency }}</flux:table.cell>
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
                            @if ($quote->lineItems->contains(fn ($item) => $item->service_id !== null))
                                <span class="text-xs text-zinc-400">{{ __('líneas generadas') }}</span>
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
                                    @if ($project)
                                        <flux:button size="xs" variant="ghost" icon="check"
                                            :tooltip="__('El cliente aceptó')"
                                            wire:click="accept({{ $quote->id }})"
                                            wire:confirm="{{ __('¿El cliente aceptó? Se creará una línea cobrable por cada renglón.') }}" />
                                    @else
                                        <flux:button size="xs" variant="ghost" icon="check"
                                            :tooltip="__('El cliente aceptó')"
                                            wire:click="openAcceptModal({{ $quote->id }})" />
                                    @endif
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
                        @if ($quotesTab === 'archivadas')
                            {{ __('Nada archivado todavía: aquí caen las aceptadas, las rechazadas y las que expiraron.') }}
                        @else
                            {{ __('Sin cotizaciones pendientes. Aquí vive lo que ya ofreciste y todavía no te contestan.') }}
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

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
                                    wire:click="removeLineItem({{ $index }})" />
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

            @if ($editingQuoteId && ! $project)
                <flux:switch wire:model="quoteIsProject" :label="__('Es un proyecto')"
                    :description="__('Al aceptarse abre un proyecto y las líneas cobrables de sus renglones nacen dentro. Los de hosting, SSL, dominio y correo siempre cuelgan del cliente. Apagado, todo queda suelto. Esto también se puede decidir hasta que se acepte.')" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeQuoteModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="quote-accept" class="md:w-96" wire:close="closeAcceptModal">
        <form wire:submit="confirmAccept" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('El cliente aceptó') }}</flux:heading>
            <flux:text class="text-zinc-400">{{ __('Se creará una línea cobrable por cada renglón.') }}</flux:text>

            <flux:switch wire:model="acceptAsProject" :label="__('Es un proyecto')"
                :description="__('Las líneas cobrables de sus renglones nacen dentro de un proyecto nuevo. Los de hosting, SSL, dominio y correo siempre cuelgan del cliente. Apagado, todo queda suelto.')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeAcceptModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Aceptar cotización') }}</flux:button>
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
