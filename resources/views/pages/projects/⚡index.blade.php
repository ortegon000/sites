<?php

use App\Enums\ClientType;
use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?string $statusFilter = null;

    public ?int $editingProjectId = null;

    public string $name = '';

    public ?string $description = null;

    public ?int $client_id = null;

    public string $status = '';

    public ?string $started_at = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Project::class);
    }

    /**
     * @return array<int, ProjectStatus>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return ProjectStatus::cases();
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
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', Project::class);

        $this->reset(['editingProjectId', 'name', 'description', 'client_id', 'started_at']);
        $this->status = ProjectStatus::Activo->value;
        $this->resetValidation();

        $this->modal('project-form')->show();
    }

    public function openEditModal(int $projectId): void
    {
        $project = Project::findOrFail($projectId);

        Gate::authorize('update', $project);

        $this->editingProjectId = $project->id;
        $this->name = $project->name;
        $this->description = $project->description;
        $this->client_id = $project->client_id;
        $this->status = $project->status->value;
        $this->started_at = $project->started_at?->toDateString();
        $this->resetValidation();

        $this->modal('project-form')->show();
    }

    public function save(): void
    {
        $project = $this->editingProjectId ? Project::findOrFail($this->editingProjectId) : null;

        Gate::authorize($project ? 'update' : 'create', $project ?? Project::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'client_id' => ['required', 'exists:clients,id'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'started_at' => ['nullable', 'date'],
        ]);

        if ($project) {
            $project->update($validated);
        } else {
            Project::create($validated);
        }

        $this->modal('project-form')->close();

        Flux::toast(variant: 'success', text: $project ? __('Proyecto actualizado.') : __('Proyecto creado.'));
    }

    public function delete(int $projectId): void
    {
        $project = Project::findOrFail($projectId);

        Gate::authorize('delete', $project);

        $project->delete();

        Flux::toast(variant: 'success', text: __('Proyecto eliminado.'));
    }

    public function closeFormModal(): void
    {
        $this->modal('project-form')->close();
    }

    public function render()
    {
        return $this->view()->title(__('Proyectos'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
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
    </div>

    <flux:table :paginate="$this->projects">
        <flux:table.columns>
            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
            <flux:table.column>{{ __('Cliente') }}</flux:table.column>
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
                    <flux:table.cell colspan="4" class="text-center text-zinc-400">
                        {{ __('Sin resultados.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="project-form" class="md:w-96">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:heading size="lg">
                {{ $editingProjectId ? __('Editar') : __('Nuevo') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Nombre')" required autofocus />

            <flux:select wire:model="client_id" :label="__('Cliente')">
                <flux:select.option value="">{{ __('Selecciona un cliente') }}</flux:select.option>
                @foreach ($this->clientOptions as $client)
                    <flux:select.option value="{{ $client->id }}">{{ $client->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="description" :label="__('Descripción')" rows="3" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input type="date" wire:model="started_at" :label="__('Fecha de inicio')" />

                <flux:select wire:model="status" :label="__('Estatus')">
                    @foreach ($this->statusOptions as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

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
</div>
