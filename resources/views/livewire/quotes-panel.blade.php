<flux:card class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-col gap-1">
            <flux:heading size="lg">{{ __('Cotizaciones') }}</flux:heading>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Trabajo ofrecido y todavía sin aceptar. Aceptarla genera, por cada renglón, su línea cobrable.') }}</flux:text>
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
                            <span class="font-medium">{{ $quote->name }}</span>
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
                    <flux:table.cell class="font-medium tabular-nums">{{ number_format((float) $quote->amount_total, 2) }} {{ $quote->currency }}</flux:table.cell>
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
                        @include('partials.quotes.row-actions', ['quote' => $quote])
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-8 text-center text-zinc-400">
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

    @include('partials.quotes.form-modal')
    @include('partials.quotes.accept-modal')
    @include('partials.quotes.reject-modal')
</flux:card>
