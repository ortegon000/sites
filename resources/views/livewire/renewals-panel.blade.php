<flux:card class="flex flex-col gap-5">
    <x-panel-header
        :title="__('Renovaciones')"
        :description="__('Dominios, licencias y servicios anuales que caducan, y qué se le dijo al cliente.')" />

    <flux:radio.group wire:model.live="renewalsTab" variant="segmented" size="sm" class="self-start">
        <flux:radio value="abiertas">{{ __('Abiertas (:count)', ['count' => $this->renewalCounts['abiertas']]) }}</flux:radio>
        <flux:radio value="historial">{{ __('Historial (:count)', ['count' => $this->renewalCounts['historial']]) }}</flux:radio>
    </flux:radio.group>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Qué caduca') }}</flux:table.column>
            <flux:table.column>{{ __('Vence') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Costo') }}</flux:table.column>
            <flux:table.column>{{ __('Ciclo') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->renewals as $renewal)
                <flux:table.row wire:key="client-renewal-{{ $renewal->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium">{{ $renewal->subject() }}</span>
                            <span class="text-xs text-zinc-400">
                                {{ $renewal->kindLabel() }}
                                @if ($renewal->notes)
                                    · {{ $renewal->notes }}
                                @endif
                            </span>
                            @if ($renewal->amount !== null)
                                <span class="text-xs font-medium tabular-nums md:hidden">{{ number_format((float) $renewal->amount, 2) }} {{ $renewal->currency }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $renewal->due_date->format('d/m/Y') }}</span>
                            @if ($renewal->isOpen())
                                <span @class([
                                    'text-xs',
                                    'font-medium text-red-600 dark:text-red-400' => $renewal->daysLeft() < 0,
                                    'font-medium text-amber-600 dark:text-amber-500' => $renewal->daysLeft() >= 0 && $renewal->daysLeft() <= 30,
                                    'text-zinc-500 dark:text-zinc-400' => $renewal->daysLeft() > 30,
                                ])>
                                    {{ $renewal->daysLeft() < 0
                                        ? __('venció hace :days días', ['days' => abs($renewal->daysLeft())])
                                        : __('en :days días', ['days' => $renewal->daysLeft()]) }}
                                </span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="hidden font-medium tabular-nums md:table-cell">
                        {{ $renewal->amount !== null ? number_format((float) $renewal->amount, 2).' '.$renewal->currency : '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col gap-1">
                            <flux:badge size="sm" :color="$renewal->status->color()">{{ $renewal->status->label() }}</flux:badge>
                            @if ($renewal->notified_at)
                                <span class="text-xs whitespace-nowrap text-zinc-400">{{ __('avisado :date', ['date' => $renewal->notified_at->format('d/m/y')]) }}</span>
                            @endif
                            @if ($renewal->service)
                                <span class="text-xs text-zinc-400">{{ __('línea generada') }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('update', $client)
                            <div class="flex items-center justify-end gap-1">
                                @if ($renewal->isOpen())
                                    <flux:button size="xs" icon="check"
                                        wire:click="markRenewed({{ $renewal->id }})"
                                        wire:confirm="{{ __('¿Registrar que renovó? Se empuja la fecha un año y se genera la línea cobrable.') }}">
                                        {{ __('Renovó') }}
                                    </flux:button>
                                @endif

                                <flux:dropdown align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Más acciones')" />

                                    <flux:menu>
                                        @if ($renewal->isOpen())
                                            <flux:menu.item icon="envelope"
                                                wire:click="notifyClient({{ $renewal->id }})"
                                                wire:confirm="{{ __('¿Mandar el aviso de renovación a los contactos de esta empresa?') }}">
                                                {{ __('Avisar al cliente') }}
                                            </flux:menu.item>
                                        @endif
                                        <flux:menu.item icon="pencil" wire:click="openAmountModal({{ $renewal->id }})">{{ __('Costo y notas') }}</flux:menu.item>
                                        @if ($renewal->isOpen())
                                            <flux:menu.separator />
                                            <flux:menu.item icon="no-symbol" variant="danger"
                                                wire:click="markNotRenewed({{ $renewal->id }})"
                                                wire:confirm="{{ __('¿Registrar que no renovó? Se dará de baja.') }}">
                                                {{ __('No renovó') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <x-empty-state>{{ $renewalsTab === 'historial'
                            ? __('Sin ciclos cerrados todavía.')
                            : __('Nada por renovar. Los ciclos se abren solos dos meses antes: si esperabas algo aquí, revisa que el dominio o la licencia tenga capturada su fecha.') }}</x-empty-state>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="client-renewal-form" class="md:w-96">
        <form wire:submit="saveRenewal" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Costo de la renovación') }}</flux:heading>

            <flux:input wire:model="renewalAmount" type="number" step="0.01" :label="__('Costo')"
                :description="__('Es lo que se cobrará al renovar, y lo que ve el cliente en su aviso.')" />

            <flux:textarea wire:model="renewalNotes" :label="__('Notas')" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeAmountModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:card>
