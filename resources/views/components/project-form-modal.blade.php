@props([
    'editingProjectId' => null,
    'typeOptions' => [],
    'statusOptions' => [],
    'billingFrequencyOptions' => [],
    'templateServices' => [],
    /** Los clientes entre los que se puede elegir; null cuando el contexto ya lo fija. */
    'clients' => null,
])

{{--
    El formulario de proyecto. Vive aquí porque se abre desde dos lados —el
    listado general y la tarjeta de la ficha del cliente— y los servicios
    sugeridos por tipo de proyecto tienen que portarse igual en los dos.
    El estado y las acciones (`save`, `closeFormModal`) son del componente
    Livewire que lo incluye, vía App\Concerns\ManagesProjectForm.
--}}
<flux:modal name="project-form" class="md:w-[34rem]">
    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:heading size="lg">
            {{ $editingProjectId ? __('Editar') : __('Nuevo') }}
        </flux:heading>

        <flux:input wire:model="name" :label="__('Nombre')" required autofocus />

        @if ($clients !== null)
            <flux:select wire:model="client_id" :label="__('Cliente')">
                <flux:select.option value="">{{ __('Selecciona un cliente') }}</flux:select.option>
                @foreach ($clients as $clientOption)
                    <flux:select.option value="{{ $clientOption->id }}">{{ $clientOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:select wire:model.live="type" :label="__('Tipo de proyecto')">
            @foreach ($typeOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:textarea wire:model="description" :label="__('Descripción')" rows="3" />

        <div class="grid grid-cols-2 gap-4">
            <flux:input type="date" wire:model="started_at" :label="__('Fecha de inicio')" />

            <flux:select wire:model="status" :label="__('Estatus')">
                @foreach ($statusOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($templateServices !== [])
            <flux:separator />

            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <flux:heading size="sm">{{ __('Servicios sugeridos') }}</flux:heading>
                    <flux:text class="text-xs text-zinc-400">
                        {{ __('Según el tipo de proyecto. Desmarca lo que no aplique y captura los montos.') }}
                    </flux:text>
                </div>

                <div class="grid grid-cols-[auto_minmax(0,1fr)_9rem_7rem] items-center gap-2 text-xs text-zinc-400">
                    <span></span>
                    <span>{{ __('Servicio') }}</span>
                    <span>{{ __('Facturación') }}</span>
                    <span>{{ __('Monto') }}</span>
                </div>

                @foreach ($templateServices as $index => $templateService)
                    <div wire:key="template-service-{{ $index }}"
                        class="grid grid-cols-[auto_minmax(0,1fr)_9rem_7rem] items-center gap-2">
                        <flux:checkbox wire:model.live="templateServices.{{ $index }}.enabled" />

                        <flux:input wire:model="templateServices.{{ $index }}.name" size="sm" />

                        <flux:select wire:model="templateServices.{{ $index }}.billing_frequency" size="sm">
                            @foreach ($billingFrequencyOptions as $option)
                                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="templateServices.{{ $index }}.amount" type="number" step="0.01"
                            size="sm" :disabled="! $templateService['enabled']" />
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="closeFormModal">
                {{ __('Cancelar') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Guardar') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
