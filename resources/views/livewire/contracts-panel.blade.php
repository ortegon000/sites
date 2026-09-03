<flux:card class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-col">
            <flux:heading size="lg">{{ __('Contratos') }}</flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ __('Se generan con los servicios y montos que ya están capturados.') }}</flux:text>
        </div>

        @can('update', $client)
            <flux:button size="sm" icon="plus" wire:click="openDraftModal">{{ __('Generar contrato') }}</flux:button>
        @endcan
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Folio') }}</flux:table.column>
            <flux:table.column>{{ __('Título') }}</flux:table.column>
            <flux:table.column>{{ __('Vigencia') }}</flux:table.column>
            <flux:table.column>{{ __('Ampara') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->contracts as $contract)
                <flux:table.row wire:key="contract-{{ $contract->id }}">
                    <flux:table.cell>{{ $contract->number }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $contract->title }}</span>
                            @if (! $project && $contract->project)
                                <span class="text-xs text-zinc-400">{{ $contract->project->name }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $contract->starts_on->format('d/m/Y') }} — {{ $contract->ends_on?->format('d/m/Y') ?? __('indefinida') }}</span>
                            @if ($contract->isExpired())
                                <span class="text-xs text-amber-600 dark:text-amber-500">{{ __('vigencia terminada') }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ trans_choice('{0}Ningún servicio|{1}1 servicio|[2,*]:count servicios', $contract->services->count(), ['count' => $contract->services->count()]) }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col gap-1">
                            <flux:badge size="sm" :color="$contract->status->color()">{{ $contract->status->label() }}</flux:badge>
                            @if ($contract->signed_at)
                                <span class="text-xs text-zinc-400">
                                    {{ __('firmó :name el :date', ['name' => $contract->signed_by, 'date' => $contract->signed_at->format('d/m/Y')]) }}
                                </span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="xs" variant="ghost" icon="document-text"
                                :tooltip="__('Ver o editar el texto')"
                                wire:click="openBodyModal({{ $contract->id }})" />

                            <flux:button size="xs" variant="ghost" icon="printer"
                                :tooltip="__('Versión imprimible')"
                                :href="route('contracts.print', $contract)" target="_blank" />

                            @can('update', $client)
                                @if ($contract->status === \App\Enums\ContractStatus::Borrador)
                                    <flux:button size="xs" variant="ghost" icon="paper-airplane"
                                        :tooltip="__('Marcar como enviado')"
                                        wire:click="markSent({{ $contract->id }})" />
                                @endif

                                @if ($contract->isEditable())
                                    <flux:button size="xs" variant="ghost" icon="check"
                                        :tooltip="__('El cliente firmó')"
                                        wire:click="openSignModal({{ $contract->id }})" />

                                    <flux:button size="xs" variant="ghost" icon="no-symbol"
                                        :tooltip="__('Cancelar contrato')"
                                        wire:click="cancel({{ $contract->id }})"
                                        wire:confirm="{{ __('¿Cancelar este contrato?') }}" />
                                @endif
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-400">
                        {{ __('Sin contratos. Se generan con los servicios que ya tiene capturados.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="contract-draft" class="md:w-[32rem]">
        <form wire:submit="draft" class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('Generar contrato') }}</flux:heading>
                <flux:text class="text-zinc-400">{{ __('El texto se arma con estos datos y después lo puedes editar.') }}</flux:text>
            </div>

            <flux:input wire:model="contractTitle" :label="__('Título')" required />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="contractStartsOn" type="date" :label="__('Inicio de vigencia')" required />
                <flux:input wire:model="contractEndsOn" type="date" :label="__('Fin de vigencia')"
                    :description="__('Vacío: vigencia indefinida.')" />
            </div>

            <div class="flex flex-col gap-2">
                <flux:heading size="sm">{{ __('Servicios que ampara') }}</flux:heading>

                @forelse ($this->contractableServices as $service)
                    <flux:checkbox wire:model="selectedServices" value="{{ $service->id }}"
                        :label="$service->name.' · '.$service->billing_frequency->label().' · '.number_format((float) $service->amount, 2).' '.$service->currency" />
                @empty
                    <flux:text class="text-zinc-400">{{ __('Este cliente no tiene servicios activos que amparar.') }}</flux:text>
                @endforelse
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeDraftModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Generar') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="contract-body" class="md:w-[48rem]" wire:close="closeBodyModal">
        @if ($this->editingContract)
            <form wire:submit="saveBody" class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ $this->editingContract->number }}</flux:heading>
                    <flux:text class="text-zinc-400">{{ $this->editingContract->title }}</flux:text>
                </div>

                @if ($this->editingContract->isEditable())
                    <flux:textarea wire:model="contractBody" :label="__('Texto del contrato')" rows="18" class="font-mono text-xs" />
                    <flux:textarea wire:model="contractNotes" :label="__('Notas internas')" rows="2"
                        :description="__('No salen en el documento.')" />
                @else
                    <flux:text class="text-zinc-400">{{ __('Este contrato ya está firmado, así que su texto quedó congelado.') }}</flux:text>
                    <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg border border-zinc-200 p-3 font-mono text-xs dark:border-zinc-700">{{ $this->editingContract->body }}</pre>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="closeBodyModal">{{ __('Cerrar') }}</flux:button>
                    @if ($this->editingContract->isEditable())
                        <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
                    @endif
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="contract-signature" class="md:w-96" wire:close="closeSignModal">
        <form wire:submit="sign" class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('Registrar la firma') }}</flux:heading>
                <flux:text class="text-zinc-400">{{ __('Al firmarse, el texto queda congelado.') }}</flux:text>
            </div>

            <flux:input wire:model="signedBy" :label="__('Quién firmó por el cliente')" required />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeSignModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:card>
