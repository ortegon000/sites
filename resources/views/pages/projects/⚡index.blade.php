<?php

use App\Actions\Projects\CreateProjectFromTemplate;
use App\Concerns\ManagesProjectForm;
use App\Enums\ClientType;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use ManagesProjectForm;
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?string $statusFilter = null;

    public ?string $typeFilter = null;

    #[Url(as: 'agencia')]
    public ?int $agencyFilter = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Project::class);
    }

    /**
     * La agencia es la primera columna de la hoja del dueño: "en qué anda lo de
     * AgenciaEfe5" se contesta filtrando aquí. Solo la ven admin y staff, que
     * son quienes tienen acceso a las agencias.
     */
    #[Computed]
    public function agencyOptions()
    {
        return Agency::query()->orderBy('name')->get();
    }

    #[Computed]
    public function clientOptions()
    {
        return Client::query()->where('type', ClientType::Client)->orderBy('name')->get();
    }

    #[Computed]
    public function projects()
    {
        return Project::query()
            ->with('client')
            ->when(auth()->user()->isCollaborator(), fn ($query) => $query->whereHas('users', fn ($q) => $q->whereKey(auth()->id())))
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
            /** La agencia del proyecto es la de su cliente: no hay otra. */
            ->when($this->agencyFilter, fn ($query) => $query->whereHas('client', fn ($q) => $q->where('agency_id', $this->agencyFilter)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function save(CreateProjectFromTemplate $createProjectFromTemplate): void
    {
        $this->saveProject($createProjectFromTemplate);
    }

    public function delete(int $projectId): void
    {
        $project = Project::findOrFail($projectId);

        Gate::authorize('delete', $project);

        $project->delete();

        Flux::toast(variant: 'success', text: __('Proyecto eliminado.'));
    }

    public function render()
    {
        return $this->view()->title(__('Proyectos'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-breadcrumbs :items="[['label' => __('Proyectos')]]" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Proyectos') }}</flux:heading>

        @can('create', \App\Models\Project::class)
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('Nuevo') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Buscar por nombre...')" class="max-w-sm" />

        <flux:select wire:model.live="statusFilter" :placeholder="__('Todos los estatus')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todos los estatus') }}</flux:select.option>
            @foreach ($this->statusOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="typeFilter" :placeholder="__('Todos los tipos')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todos los tipos') }}</flux:select.option>
            @foreach ($this->typeOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
            <flux:select wire:model.live="agencyFilter" :placeholder="__('Todas las agencias')" class="max-w-xs">
                <flux:select.option value="">{{ __('Todas las agencias') }}</flux:select.option>
                @foreach ($this->agencyOptions as $agency)
                    <flux:select.option value="{{ $agency->id }}">{{ $agency->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    <flux:table :paginate="$this->projects">
        <flux:table.columns>
            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
            <flux:table.column>{{ __('Cliente') }}</flux:table.column>
            <flux:table.column>{{ __('Tipo') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->projects as $project)
                <flux:table.row wire:key="project-{{ $project->id }}">
                    <flux:table.cell>
                        <flux:link :href="route('projects.show', $project)" wire:navigate>{{ $project->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $project->client->name }}</flux:table.cell>
                    <flux:table.cell>{{ $project->type->label() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $project->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            @can('update', $project)
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="openEditModal({{ $project->id }})" />
                            @endcan
                            @can('delete', $project)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $project->id }})" wire:confirm="{{ __('¿Eliminar este proyecto?') }}" />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-400">
                        {{ __('Sin resultados.') }}
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
        :template-services="$templateServices"
        :clients="$this->clientOptions" />
</div>
