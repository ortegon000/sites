<flux:card class="flex flex-col gap-5">
    <x-panel-header :title="__('Proyectos')" :count="$this->projects->count()">
        <x-slot:actions>
            @can('create', \App\Models\Project::class)
                <flux:button size="sm" icon="plus" wire:click="openCreateModal">
                    {{ __('Agregar proyecto') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-panel-header>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
            <flux:table.column>{{ __('Tipo') }}</flux:table.column>
            <flux:table.column>{{ __('Servicios') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->projects as $project)
                <flux:table.row wire:key="client-project-{{ $project->id }}">
                    <flux:table.cell>
                        <flux:link :href="route('projects.show', $project)" wire:navigate class="font-medium">{{ $project->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $project->type->label() }}</flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $project->services_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$project->status->color()">{{ $project->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('update', $project)
                            <div class="flex justify-end">
                                <flux:button size="xs" variant="ghost" icon="pencil"
                                    :tooltip="__('Editar')"
                                    wire:click="openEditModal({{ $project->id }})"
                                    :aria-label="__('Editar')" />
                            </div>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <x-empty-state>{{ __('Sin proyectos. No todos los clientes necesitan uno: los de puro hosting viven de sus dominios y servicios.') }}</x-empty-state>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <x-project-form-modal
        :editing-project-id="$editingProjectId"
        :type-options="$this->typeOptions"
        :status-options="$this->statusOptions"
        :billing-frequency-options="$this->billingFrequencyOptions"
        :template-services="$templateServices" />
</flux:card>
