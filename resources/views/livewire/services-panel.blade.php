<flux:card class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-col">
            <flux:heading size="lg">{{ $project ? __('Servicios del proyecto') : __('Líneas cobrables') }}</flux:heading>
            @unless ($project)
                <flux:text class="text-xs text-zinc-400">{{ __('Trabajos y servicios que no pasan por un proyecto.') }}</flux:text>
            @endunless
        </div>

        @can('update', $client)
            <flux:button size="sm" variant="ghost" icon="plus" wire:click="openServiceModal">
                {{ __('Con más detalle') }}
            </flux:button>
        @endcan
    </div>

    @can('update', $client)
        <form wire:submit="quickCapture" class="flex flex-wrap items-end gap-2">
            <flux:input wire:model="quickStartsOn" type="date" size="sm" :label="__('Fecha')" class="w-40" />

            <flux:input wire:model="quickName" size="sm" :label="__('Concepto')" :placeholder="__('Renovación anual, mejora continua...')" class="min-w-56 flex-1" />

            <flux:input wire:model="quickAmount" type="number" step="0.01" size="sm" :label="__('Monto')" class="w-32" />

            <flux:select wire:model="quickFrequency" size="sm" :label="__('Facturación')" class="w-40">
                @foreach ($this->billingFrequencyOptions as $option)
                    @if ($option !== \App\Enums\ServiceBillingFrequency::Installment)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endif
                @endforeach
            </flux:select>

            <flux:button type="submit" size="sm" variant="primary" icon="plus">{{ __('Capturar') }}</flux:button>
        </form>

        <flux:error name="quickName" />
        <flux:error name="quickAmount" />
    @endcan

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Concepto') }}</flux:table.column>
            <flux:table.column>{{ __('Facturación') }}</flux:table.column>
            <flux:table.column>{{ __('Monto') }}</flux:table.column>
            <flux:table.column>{{ __('Qué incluye') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->services as $service)
                <flux:table.row wire:key="service-{{ $service->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $service->name }}</span>
                            <span class="text-xs text-zinc-400">
                                {{ $service->category->label() }}
                                @if ($service->domain)
                                    · {{ $service->domain->name }}
                                @endif
                                · {{ $service->starts_on->format('d/m/Y') }}
                            </span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $service->billing_frequency->label() }}</flux:table.cell>
                    <flux:table.cell>{{ number_format((float) $service->amount, 2) }} {{ $service->currency }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($service->items_count > 0)
                            <flux:badge size="sm" :color="$service->pending_items_count > 0 ? 'amber' : 'green'">
                                {{ $service->items_count - $service->pending_items_count }}/{{ $service->items_count }}
                            </flux:badge>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $service->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="xs" variant="ghost" icon="list-bullet"
                                :tooltip="__('Qué incluye')"
                                wire:click="openItemsModal({{ $service->id }})" />

                            @can('update', $client)
                                @if ($service->status !== \App\Enums\ServiceStatus::Cancelado)
                                    <flux:button size="xs" variant="ghost" icon="no-symbol"
                                        :tooltip="__('Cancelar servicio')"
                                        wire:click="cancelService({{ $service->id }})"
                                        wire:confirm="{{ __('¿Cancelar este servicio? Dejará de generar cobros y conservará los que ya tiene.') }}" />
                                @endif

                                <flux:button size="xs" variant="ghost" icon="trash"
                                    :tooltip="__('Eliminar servicio')"
                                    wire:click="deleteService({{ $service->id }})"
                                    wire:confirm="{{ __('¿Eliminar este servicio? Se borrarán también sus cobros pendientes y sus cuotas.') }}" />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-400">
                        {{ $project ? __('Sin servicios todavía.') : __('Sin líneas sueltas. Captura una arriba: fecha, concepto y monto.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="service-items" class="md:w-[32rem]" wire:close="closeItemsModal">
        @if ($this->itemsService)
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Qué incluye') }}</flux:heading>
                    <flux:text class="text-zinc-400">
                        {{ $this->itemsService->name }} · {{ number_format((float) $this->itemsService->amount, 2) }} {{ $this->itemsService->currency }}
                    </flux:text>
                    <flux:text class="text-xs text-zinc-400">
                        {{ __('El alcance de la línea: las visitas que cubre el anual, los entregables que van al contrato. Marcarlos no cobra de más, porque el monto es del servicio. Los pendientes del trabajo son las tareas del proyecto.') }}
                    </flux:text>
                </div>

                <div class="flex flex-col gap-2">
                    @forelse ($this->itemsService->items as $item)
                        <div wire:key="service-item-{{ $item->id }}" class="flex items-center justify-between gap-3 border-b border-zinc-100 pb-2 text-sm last:border-0 dark:border-zinc-700">
                            <div class="flex items-center gap-3">
                                @can('update', $client)
                                    <flux:button size="xs" variant="ghost"
                                        :icon="$item->isDone() ? 'check-circle' : 'minus-circle'"
                                        :tooltip="$item->isDone() ? __('Marcar pendiente') : __('Marcar hecha')"
                                        wire:click="toggleItem({{ $item->id }})" />
                                @endcan

                                <div class="flex flex-col">
                                    <span class="{{ $item->isDone() ? 'text-zinc-400 line-through' : '' }}">{{ $item->description }}</span>
                                    <span class="text-xs text-zinc-400">
                                        {{ $item->due_date?->format('d/m/Y') ?? __('Sin fecha') }}
                                        @if ($item->isDone())
                                            · {{ __('hecha el :date', ['date' => $item->completed_at->format('d/m/Y')]) }}
                                        @endif
                                    </span>
                                </div>
                            </div>

                            @can('update', $client)
                                <flux:button size="xs" variant="ghost" icon="trash"
                                    wire:click="deleteItem({{ $item->id }})"
                                    wire:confirm="{{ __('¿Quitar esto del alcance de la línea?') }}" />
                            @endcan
                        </div>
                    @empty
                        <flux:text class="text-zinc-400">{{ __('Sin nada capturado: el monto no desglosa qué cubre.') }}</flux:text>
                    @endforelse
                </div>

                @can('update', $client)
                    <flux:separator />

                    <form wire:submit="addItem" class="flex flex-wrap items-end gap-2">
                        <flux:input wire:model="itemDescription" size="sm" :label="__('Concepto')" :placeholder="__('Visita de mantenimiento')" class="min-w-56 flex-1" />
                        <flux:input wire:model="itemDueDate" type="date" size="sm" :label="__('Fecha')" class="w-40" />
                        <flux:button type="submit" size="sm" variant="primary">{{ __('Agregar') }}</flux:button>
                    </form>

                    <flux:error name="itemDescription" />
                @endcan
            </div>
        @endif
    </flux:modal>

    <flux:modal name="service-form" class="md:w-96">
        <form wire:submit="saveService" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Nuevo servicio') }}</flux:heading>

            <flux:input wire:model="serviceName" :label="__('Nombre')" required autofocus />
            <flux:textarea wire:model="serviceDescription" :label="__('Descripción')" rows="2" />

            <flux:select wire:model.live="serviceCategory" :label="__('Categoría')">
                @foreach ($this->serviceCategoryOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->domainOptions->isNotEmpty() && \App\Enums\ServiceCategory::from($serviceCategory ?: 'other')->belongsToDomain())
                <flux:select wire:model="serviceDomainId" :label="__('Dominio')">
                    <flux:select.option value="">{{ __('Sin dominio específico') }}</flux:select.option>
                    @foreach ($this->domainOptions as $domain)
                        <flux:select.option value="{{ $domain->id }}">{{ $domain->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="billingFrequency" :label="__('Facturación')">
                <flux:select.option value="">{{ __('Selecciona una opción') }}</flux:select.option>
                @foreach ($this->billingFrequencyOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="amount" type="number" step="0.01" :label="__('Monto por cobro')" />
                <flux:input wire:model="currency" :label="__('Moneda')" maxlength="3" />
            </div>

            @if ($billingFrequency === \App\Enums\ServiceBillingFrequency::Installment->value)
                <flux:input wire:model="installmentsCount" type="number" min="1" max="60" :label="__('Número de pagos')" />
            @endif

            <div class="grid grid-cols-2 gap-4">
                <flux:input type="date" wire:model="startsOn" :label="__('Fecha de inicio')" />

                <flux:select wire:model="serviceStatus" :label="__('Estatus')">
                    @foreach ($this->serviceStatusOptions as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeServiceModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:card>
