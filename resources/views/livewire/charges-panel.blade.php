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
            <flux:table.column class="hidden md:table-cell">
                <span class="flex items-baseline gap-1">
                    {{ __('Monto') }}
                    @if ($this->singleCurrency)
                        <span class="text-[0.65rem] font-normal">{{ $this->singleCurrency }}</span>
                    @endif
                </span>
            </flux:table.column>
            <flux:table.column class="hidden md:table-cell">
                <span class="flex items-baseline gap-1">
                    {{ __('Restante') }}
                    @if ($this->singleCurrency)
                        <span class="text-[0.65rem] font-normal">{{ $this->singleCurrency }}</span>
                    @endif
                </span>
            </flux:table.column>
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
                    <flux:table.cell class="hidden tabular-nums md:table-cell">{{ number_format((float) $charge->amount, 2) }}@unless ($this->singleCurrency) {{ $charge->currency }}@endunless</flux:table.cell>
                    <flux:table.cell class="hidden tabular-nums md:table-cell">
                        <div class="flex flex-col">
                            <span @class(['font-semibold' => $charge->remainingAmount() > 0, 'text-zinc-400' => $charge->remainingAmount() <= 0])>{{ number_format($charge->remainingAmount(), 2) }}@unless ($this->singleCurrency) {{ $charge->currency }}@endunless</span>
                            @if ($charge->payments->isNotEmpty())
                                <span class="text-xs text-zinc-400">
                                    {{ __('abonado :amount', ['amount' => number_format($charge->paidAmount(), 2)]) }}
                                </span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('update', $client)
                            <flux:dropdown>
                                <flux:button size="xs" variant="ghost" icon-trailing="chevron-down">
                                    <flux:badge size="sm" :color="$charge->status->color()" inset="top bottom">{{ $charge->status->label() }}</flux:badge>
                                </flux:button>

                                <flux:menu>
                                    @if ($charge->status === \App\Enums\ChargeStatus::Pagado)
                                        <flux:menu.item
                                            wire:click="updateChargeStatus({{ $charge->id }}, '{{ \App\Enums\ChargeStatus::Pendiente->value }}')"
                                            wire:confirm="{{ __('¿Regresar este cobro a pendiente? Se eliminarán todos sus abonos.') }}">
                                            {{ \App\Enums\ChargeStatus::Pendiente->label() }}
                                        </flux:menu.item>
                                    @else
                                        <flux:menu.item
                                            wire:click="updateChargeStatus({{ $charge->id }}, '{{ \App\Enums\ChargeStatus::Pagado->value }}')"
                                            wire:confirm="{{ __('¿Registrar el saldo restante como abono y marcar este cobro como pagado?') }}">
                                            {{ \App\Enums\ChargeStatus::Pagado->label() }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        @else
                            <flux:badge size="sm" :color="$charge->status->color()">{{ $charge->status->label() }}</flux:badge>
                        @endcan
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('update', $client)
                            <div class="flex items-center justify-end gap-1">
                                <flux:dropdown align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Más acciones')" />

                                    <flux:menu>
                                        <flux:menu.item icon="banknotes" wire:click="openPaymentsModal({{ $charge->id }})">{{ __('Abonos') }}</flux:menu.item>
                                        @unless ($charge->status === \App\Enums\ChargeStatus::Pagado)
                                            <flux:menu.item icon="pencil" wire:click="openChargeModal({{ $charge->id }})">{{ __('Editar cobro') }}</flux:menu.item>
                                        @endunless
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

            @foreach ($this->totalsByCurrency as $currency => $totals)
                <flux:table.row wire:key="charges-total-{{ $currency }}" class="bg-zinc-50 dark:bg-white/5">
                    <flux:table.cell class="font-semibold">
                        <div class="flex flex-col">
                            <span>{{ __('Total') }} {{ $currency }}</span>
                            <span class="text-xs font-normal text-zinc-500 tabular-nums md:hidden dark:text-zinc-400">
                                {{ number_format($totals['amount'], 2) }} · {{ __('Restan') }} {{ number_format($totals['remaining'], 2) }}
                            </span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell"></flux:table.cell>
                    <flux:table.cell class="hidden font-semibold tabular-nums md:table-cell">{{ number_format($totals['amount'], 2) }}@unless ($this->singleCurrency) {{ $currency }}@endunless</flux:table.cell>
                    <flux:table.cell class="hidden tabular-nums md:table-cell">
                        <div class="flex flex-col">
                            <span class="font-semibold">{{ number_format($totals['remaining'], 2) }}</span>
                            <span class="text-xs text-zinc-400">{{ __('abonado :amount', ['amount' => number_format($totals['paid'], 2)]) }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell></flux:table.cell>
                    <flux:table.cell></flux:table.cell>
                </flux:table.row>
            @endforeach
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
            @php
                $payingCharge = $this->payingCharge;
                $isPaid = $payingCharge->status === \App\Enums\ChargeStatus::Pagado;
                $singlePayment = $payingCharge->payments->count() === 1 ? $payingCharge->payments->first() : null;
                /** Con un solo abono en un cobro pagado, su fecha ya sube al resumen y su importe es el "Abonado": la lista solo aporta si trae detalle. */
                $summarizesPayment = $isPaid && $singlePayment !== null;
                $singlePaymentDetails = $singlePayment ? collect([$singlePayment->method, $singlePayment->account, $singlePayment->reference, $singlePayment->invoice_reference ? __('Folio :folio', ['folio' => $singlePayment->invoice_reference]) : null])->filter()->join(' · ') : '';
                $showPaymentList = ! $summarizesPayment || $singlePaymentDetails !== '';
            @endphp

            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Abonos') }}</flux:heading>
                    <flux:text class="text-zinc-400">{{ $this->payingCharge->conceptLabel() }}</flux:text>
                </div>

                <div @class(['grid grid-cols-2 gap-4 text-sm', 'sm:grid-cols-4' => $isPaid, 'sm:grid-cols-3' => ! $isPaid])>
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
                    @if ($isPaid && $payingCharge->paid_at)
                        <div class="flex flex-col">
                            <span class="text-zinc-400">{{ __('Pagado el') }}</span>
                            <span>{{ $payingCharge->paid_at->format('d/m/Y') }}</span>
                        </div>
                    @endif
                </div>

                @if ($showPaymentList)
                <div class="flex flex-col gap-2">
                    @forelse ($this->payingCharge->payments as $payment)
                        <div wire:key="payment-{{ $payment->id }}" class="flex items-start justify-between gap-4 border-b border-zinc-100 pb-2 text-sm last:border-0 dark:border-zinc-700">
                            @php
                                $paymentDetails = collect([$payment->method, $payment->account, $payment->reference, $payment->invoice_reference ? __('Folio :folio', ['folio' => $payment->invoice_reference]) : null])->filter()->join(' · ');
                            @endphp
                            <div class="grid min-w-0 flex-1 grid-cols-2 gap-x-4 sm:flex sm:items-baseline sm:gap-3">
                                @unless ($summarizesPayment)
                                    <span class="font-medium whitespace-nowrap">
                                        @if ($this->payingCharge->payments->count() > 1)
                                            {{ number_format((float) $payment->amount, 2) }} ·
                                        @endif
                                        {{ $payment->paid_on->format('d/m/Y') }}
                                    </span>
                                @endunless
                                @if ($paymentDetails !== '')
                                    <span class="min-w-0 text-xs text-zinc-500 sm:truncate dark:text-zinc-400" title="{{ $paymentDetails }}">{{ $paymentDetails }}</span>
                                @endif
                            </div>
                            @can('update', $client)
                                @if ($this->payingCharge->status !== \App\Enums\ChargeStatus::Pagado)
                                    <flux:button size="xs" variant="ghost" icon="trash"
                                        :aria-label="__('Eliminar abono')"
                                        wire:click="deletePayment({{ $payment->id }})"
                                        wire:confirm="{{ __('¿Eliminar este abono?') }}" />
                                @endif
                            @endcan
                        </div>
                    @empty
                        <flux:text class="text-zinc-400">{{ __('Sin abonos todavía.') }}</flux:text>
                    @endforelse
                </div>
                @endif

                @can('update', $client)
                    <flux:separator />

                    @if ($this->payingCharge->remainingAmount() <= 0)
                        <div class="flex items-center justify-between">
                            <flux:text class="text-zinc-400">{{ __('Este cobro ya está cubierto.') }}</flux:text>
                            <flux:button variant="ghost" wire:click="closePaymentsModal">{{ __('Cerrar') }}</flux:button>
                        </div>
                    @else
                    <form wire:submit="savePayment" class="flex flex-col gap-4">
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <flux:input wire:model="paymentAmount" type="number" step="0.01" :label="__('Monto del abono')" required />
                            <flux:input wire:model="paymentPaidOn" type="date" :label="__('Fecha de pago')" required />
                            <flux:input wire:model="paymentMethod" :label="__('Método')" :placeholder="__('Transferencia')" />
                            <flux:input wire:model="paymentAccount" :label="__('Cuenta')" :placeholder="__('Banco')" />
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
