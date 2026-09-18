<flux:card class="flex flex-col gap-5">
    <x-panel-header
        :title="$project ? __('Cobros del proyecto') : __('Cobros')"
        :count="$this->charges->count()"
        :description="$project ? null : __('Todo lo cobrado y por cobrar del cliente, con o sin proyecto.')" />

    @if ($this->outstandingByCurrency !== [])
        <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm">
            @foreach ($this->outstandingByCurrency as $currency => $totals)
                <span>
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Por cobrar') }}</span>
                    <span class="font-semibold tabular-nums">{{ number_format($totals['open'], 2) }} {{ $currency }}</span>
                </span>
                @if ($totals['overdue'] > 0)
                    <span class="text-red-600 dark:text-red-400">
                        {{ __('Vencido') }}
                        <span class="font-semibold tabular-nums">{{ number_format($totals['overdue'], 2) }} {{ $currency }}</span>
                    </span>
                @endif
            @endforeach
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Concepto') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Vencimiento') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Monto') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Restante') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->charges as $charge)
                <flux:table.row wire:key="charge-{{ $charge->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium">{{ $charge->conceptLabel() }}</span>
                            @if ($charge->concept)
                                <span class="text-xs text-zinc-400">{{ $charge->service->name }}</span>
                            @endif
                            <span class="text-xs text-zinc-500 md:hidden dark:text-zinc-400">
                                {{ __('Vence') }} {{ $charge->due_date->format('d/m/Y') }} · {{ number_format((float) $charge->amount, 2) }} {{ $charge->currency }}
                            </span>
                            @if ($charge->remainingAmount() > 0)
                                <span class="text-xs font-semibold tabular-nums md:hidden">{{ __('Restan') }} {{ number_format($charge->remainingAmount(), 2) }}</span>
                            @endif
                            @unless ($project)
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    @if ($charge->service->project)
                                        <flux:link :href="route('projects.show', $charge->service->project)" wire:navigate>{{ $charge->service->project->name }}</flux:link>
                                    @else
                                        {{ __('Línea suelta') }}
                                    @endif
                                </span>
                            @endunless
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">{{ $charge->due_date->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell class="hidden tabular-nums md:table-cell">{{ number_format((float) $charge->amount, 2) }} {{ $charge->currency }}</flux:table.cell>
                    <flux:table.cell class="hidden tabular-nums md:table-cell">
                        <div class="flex flex-col">
                            <span @class(['font-semibold' => $charge->remainingAmount() > 0, 'text-zinc-400' => $charge->remainingAmount() <= 0])>{{ number_format($charge->remainingAmount(), 2) }}</span>
                            @if ($charge->payments->isNotEmpty())
                                <span class="text-xs text-zinc-400">
                                    {{ __('abonado :amount', ['amount' => number_format($charge->paidAmount(), 2)]) }}
                                </span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$charge->status->color()">
                            {{ $charge->status->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('update', $client)
                            <div class="flex items-center justify-end gap-1">
                                @if ($charge->status !== \App\Enums\ChargeStatus::Pagado)
                                    <flux:button size="xs" icon="check"
                                        wire:click="markChargeAsPaid({{ $charge->id }})"
                                        wire:confirm="{{ __('¿Registrar el saldo restante como abono y marcar este cobro como pagado?') }}">
                                        {{ __('Cobrar') }}
                                    </flux:button>
                                @endif

                                <flux:dropdown align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Más acciones')" />

                                    <flux:menu>
                                        <flux:menu.item icon="banknotes" wire:click="openPaymentsModal({{ $charge->id }})">{{ __('Abonos') }}</flux:menu.item>
                                        <flux:menu.item icon="pencil" wire:click="openChargeModal({{ $charge->id }})">{{ __('Editar cobro') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <x-empty-state>{{ __('Sin cobros todavía.') }}</x-empty-state>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="charge-form" class="md:w-96">
        <form wire:submit="saveCharge" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Editar cobro') }}</flux:heading>

            <flux:input wire:model="chargeConcept" :label="__('Concepto')"
                :description="__('Si lo dejas vacío se usa el nombre del servicio.')" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="chargeAmount" type="number" step="0.01" :label="__('Monto')" required />
                <flux:input wire:model="chargeDueDate" type="date" :label="__('Vencimiento')" required />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeChargeModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="charge-payments" class="md:w-[36rem]" wire:close="closePaymentsModal">
        @if ($this->payingCharge)
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Abonos') }}</flux:heading>
                    <flux:text class="text-zinc-400">{{ $this->payingCharge->conceptLabel() }}</flux:text>
                </div>

                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div class="flex flex-col">
                        <span class="text-zinc-400">{{ __('Monto') }}</span>
                        <span>{{ number_format((float) $this->payingCharge->amount, 2) }} {{ $this->payingCharge->currency }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-zinc-400">{{ __('Abonado') }}</span>
                        <span>{{ number_format($this->payingCharge->paidAmount(), 2) }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-zinc-400">{{ __('Restante') }}</span>
                        <span>{{ number_format($this->payingCharge->remainingAmount(), 2) }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    @forelse ($this->payingCharge->payments as $payment)
                        <div wire:key="payment-{{ $payment->id }}" class="flex items-start justify-between gap-4 border-b border-zinc-100 pb-2 text-sm last:border-0 dark:border-zinc-700">
                            <div class="flex flex-col">
                                <span>{{ number_format((float) $payment->amount, 2) }} · {{ $payment->paid_on->format('d/m/Y') }}</span>
                                <span class="text-xs text-zinc-400">
                                    {{ collect([$payment->method, $payment->account, $payment->reference, $payment->invoice_reference ? __('Folio :folio', ['folio' => $payment->invoice_reference]) : null])->filter()->join(' · ') ?: '—' }}
                                </span>
                            </div>
                            @can('update', $client)
                                <flux:button size="xs" variant="ghost" icon="trash"
                                    wire:click="deletePayment({{ $payment->id }})"
                                    wire:confirm="{{ __('¿Eliminar este abono?') }}" />
                            @endcan
                        </div>
                    @empty
                        <flux:text class="text-zinc-400">{{ __('Sin abonos todavía.') }}</flux:text>
                    @endforelse
                </div>

                @can('update', $client)
                    <flux:separator />

                    @if ($this->payingCharge->remainingAmount() <= 0)
                        <div class="flex items-center justify-between">
                            <flux:text class="text-zinc-400">{{ __('Este cobro ya está cubierto.') }}</flux:text>
                            <flux:button variant="ghost" wire:click="closePaymentsModal">{{ __('Cerrar') }}</flux:button>
                        </div>
                    @else
                    <form wire:submit="savePayment" class="flex flex-col gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="paymentAmount" type="number" step="0.01" :label="__('Monto del abono')" required />
                            <flux:input wire:model="paymentPaidOn" type="date" :label="__('Fecha de pago')" required />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="paymentMethod" :label="__('Método')" :placeholder="__('Transferencia, efectivo...')" />
                            <flux:input wire:model="paymentAccount" :label="__('Cuenta')" :placeholder="__('Banco o cuenta que recibió')" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="paymentReference" :label="__('Comprobante')" />
                            <flux:input wire:model="paymentInvoiceReference" :label="__('Folio de factura')" />
                        </div>

                        <div class="flex justify-end gap-2">
                            <flux:button variant="ghost" wire:click="closePaymentsModal">{{ __('Cerrar') }}</flux:button>
                            <flux:button type="submit" variant="primary">{{ __('Registrar abono') }}</flux:button>
                        </div>
                    </form>
                    @endif
                @endcan
            </div>
        @endif
    </flux:modal>
</flux:card>
