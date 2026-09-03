<flux:card class="flex flex-col gap-4">
    <div class="flex flex-col">
        <flux:heading size="lg">{{ $project ? __('Cobros del proyecto') : __('Cobros') }}</flux:heading>
        @unless ($project)
            <flux:text class="text-xs text-zinc-400">{{ __('Todo lo cobrado y por cobrar del cliente, con o sin proyecto.') }}</flux:text>
        @endunless
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Concepto') }}</flux:table.column>
            @unless ($project)
                <flux:table.column>{{ __('Proyecto') }}</flux:table.column>
            @endunless
            <flux:table.column>{{ __('Vencimiento') }}</flux:table.column>
            <flux:table.column>{{ __('Monto') }}</flux:table.column>
            <flux:table.column>{{ __('Restante') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->charges as $charge)
                <flux:table.row wire:key="charge-{{ $charge->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $charge->conceptLabel() }}</span>
                            @if ($charge->concept)
                                <span class="text-xs text-zinc-400">{{ $charge->service->name }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    @unless ($project)
                            <flux:table.cell>
                                @if ($charge->service->project)
                                    <flux:link :href="route('projects.show', $charge->service->project)" wire:navigate>
                                        {{ $charge->service->project->name }}
                                    </flux:link>
                                @else
                                    <span class="text-zinc-400">{{ __('Línea suelta') }}</span>
                                @endif
                            </flux:table.cell>
                        @endunless
                        <flux:table.cell>{{ $charge->due_date->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>{{ number_format((float) $charge->amount, 2) }} {{ $charge->currency }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ number_format($charge->remainingAmount(), 2) }}</span>
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
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" variant="ghost" icon="pencil"
                                    :tooltip="__('Editar cobro')"
                                    wire:click="openChargeModal({{ $charge->id }})" />

                                <flux:button size="xs" variant="ghost" icon="banknotes"
                                    :tooltip="__('Abonos')"
                                    wire:click="openPaymentsModal({{ $charge->id }})" />

                                @if ($charge->status !== \App\Enums\ChargeStatus::Pagado)
                                    <flux:button size="xs" variant="ghost" icon="check"
                                        :tooltip="__('Marcar pagado')"
                                        wire:click="markChargeAsPaid({{ $charge->id }})" />
                                @endif
                            </div>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell :colspan="$project ? 6 : 7" class="text-center text-zinc-400">
                        {{ __('Sin cobros todavía.') }}
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
