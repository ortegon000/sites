<?php

use App\Actions\Clients\SyncClientAgencyToProjects;
use App\Actions\Projects\CreateProjectFromTemplate;
use App\Enums\ClientType;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Arr;
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

    public ?string $typeFilter = null;

    public ?int $agencyFilter = null;

    public ?int $editingProjectId = null;

    public string $name = '';

    public ?string $description = null;

    public ?int $client_id = null;

    public string $status = '';

    public string $type = ProjectType::Other->value;

    public bool $includes_email = false;

    public ?string $started_at = null;

    /**
     * Services the chosen project type suggests, as editable rows. They are
     * only a starting point: staff ticks what applies and fills in the amounts,
     * which is why the enum's template carries no prices.
     *
     * @var array<int, array{enabled: bool, name: string, category: string, billing_frequency: string, amount: string}>
     */
    public array $templateServices = [];

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

    /**
     * @return array<int, ProjectType>
     */
    #[Computed]
    public function typeOptions(): array
    {
        return ProjectType::cases();
    }

    /**
     * @return array<int, ServiceBillingFrequency>
     */
    #[Computed]
    public function billingFrequencyOptions(): array
    {
        return ServiceBillingFrequency::cases();
    }

    /**
     * Reload the suggested services whenever the type changes, and seed the
     * email flag from that type. Editing an existing project leaves both alone:
     * its services already exist and its flag may have been adjusted on purpose.
     */
    public function updatedType(string $value): void
    {
        if ($this->editingProjectId !== null) {
            return;
        }

        $type = ProjectType::tryFrom($value);

        if ($type === null) {
            $this->templateServices = [];

            return;
        }

        $this->includes_email = $type->includesEmailByDefault();

        $this->templateServices = array_map(fn (array $service) => [
            'enabled' => true,
            'name' => $service['name'],
            'category' => $service['category']->value,
            'billing_frequency' => $service['billing_frequency']->value,
            'amount' => '',
        ], $type->serviceTemplate());
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
            ->when($this->agencyFilter, fn ($query) => $query->whereHas('agencies', fn ($q) => $q->whereKey($this->agencyFilter)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', Project::class);

        $this->reset(['editingProjectId', 'name', 'description', 'client_id', 'started_at', 'templateServices', 'includes_email']);
        $this->status = ProjectStatus::Activo->value;
        $this->type = ProjectType::Other->value;
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
        $this->type = $project->type->value;
        $this->includes_email = $project->includes_email;
        $this->started_at = $project->started_at?->toDateString();
        $this->templateServices = [];
        $this->resetValidation();

        $this->modal('project-form')->show();
    }

    public function save(SyncClientAgencyToProjects $syncClientAgencyToProjects, CreateProjectFromTemplate $createProjectFromTemplate): void
    {
        $project = $this->editingProjectId ? Project::findOrFail($this->editingProjectId) : null;

        Gate::authorize($project ? 'update' : 'create', $project ?? Project::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'client_id' => ['required', 'exists:clients,id'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'includes_email' => ['boolean'],
            'started_at' => ['nullable', 'date'],
            'templateServices' => ['array'],
            'templateServices.*.enabled' => ['boolean'],
            'templateServices.*.name' => ['required', 'string', 'max:255'],
            'templateServices.*.category' => ['required', Rule::enum(ServiceCategory::class)],
            'templateServices.*.billing_frequency' => ['required', Rule::enum(ServiceBillingFrequency::class)],
            'templateServices.*.amount' => ['exclude_if:templateServices.*.enabled,false', 'required', 'numeric', 'min:0'],
        ]);

        $isNew = $project === null;
        $attributes = Arr::except($validated, ['templateServices']);

        if ($project) {
            $project->update($attributes);
        } else {
            $project = Project::create($attributes);
        }

        if ($isNew) {
            $createProjectFromTemplate->handle(
                $project,
                array_values(array_filter($validated['templateServices'] ?? [], fn (array $service) => $service['enabled'])),
            );
        }

        $syncClientAgencyToProjects->handle($project->client);

        $this->modal('project-form')->close();

        Flux::toast(variant: 'success', text: $isNew ? __('Proyecto creado.') : __('Proyecto actualizado.'));
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

    <flux:modal name="project-form" class="md:w-[34rem]">
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

            <flux:select wire:model.live="type" :label="__('Tipo de proyecto')">
                @foreach ($this->typeOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:checkbox wire:model="includes_email" :label="__('Incluye correo')"
                :description="__('Habilita administrar buzones en los dominios de este proyecto.')" />

            <flux:textarea wire:model="description" :label="__('Descripción')" rows="3" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input type="date" wire:model="started_at" :label="__('Fecha de inicio')" />

                <flux:select wire:model="status" :label="__('Estatus')">
                    @foreach ($this->statusOptions as $option)
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
                                @foreach ($this->billingFrequencyOptions as $option)
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
</div>
