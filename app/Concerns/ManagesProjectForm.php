<?php

namespace App\Concerns;

use App\Actions\Projects\CreateProjectFromTemplate;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * El formulario de proyecto: lo mismo se captura desde el listado general que
 * desde la tarjeta de proyectos de la ficha del cliente, servicios sugeridos
 * incluidos, así que ambos comparten estado, validación y guardado.
 *
 * Quien lo use en un contexto que ya sabe de qué cliente se trata sobrescribe
 * `lockedProjectClientId()`; ahí el selector de cliente no se pinta y el id de
 * la ficha manda sobre lo que llegue del navegador.
 */
trait ManagesProjectForm
{
    public ?int $editingProjectId = null;

    public string $name = '';

    public ?string $description = null;

    public ?int $client_id = null;

    public string $status = '';

    public string $type = ProjectType::Other->value;

    public ?string $started_at = null;

    /**
     * Services the chosen project type suggests, as editable rows. They are
     * only a starting point: staff ticks what applies and fills in the amounts,
     * which is why the enum's template carries no prices.
     *
     * @var array<int, array{enabled: bool, name: string, category: string, billing_frequency: string, amount: string}>
     */
    public array $templateServices = [];

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
     * Reload the suggested services whenever the type changes. Editing an
     * existing project leaves them alone: its services already exist.
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

        $this->templateServices = array_map(fn (array $service) => [
            'enabled' => true,
            'name' => $service['name'],
            'category' => $service['category']->value,
            'billing_frequency' => $service['billing_frequency']->value,
            'amount' => '',
        ], $type->serviceTemplate());
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', Project::class);

        $this->resetProjectForm();

        $this->modal('project-form')->show();
    }

    public function openEditModal(int $projectId): void
    {
        $project = $this->findProjectToEdit($projectId);

        Gate::authorize('update', $project);

        $this->editingProjectId = $project->id;
        $this->name = $project->name;
        $this->description = $project->description;
        $this->client_id = $project->client_id;
        $this->status = $project->status->value;
        $this->type = $project->type->value;
        $this->started_at = $project->started_at?->toDateString();
        $this->templateServices = [];
        $this->resetValidation();

        $this->modal('project-form')->show();
    }

    public function closeFormModal(): void
    {
        $this->modal('project-form')->close();
    }

    protected function resetProjectForm(): void
    {
        $this->reset(['editingProjectId', 'name', 'description', 'client_id', 'started_at', 'templateServices']);
        $this->status = ProjectStatus::Activo->value;
        $this->type = ProjectType::Other->value;
        $this->client_id = $this->lockedProjectClientId();
        $this->resetValidation();
    }

    /**
     * El cliente que el contexto ya tiene decidido, si lo hay. El listado
     * general devuelve null porque ahí se elige en el formulario.
     */
    protected function lockedProjectClientId(): ?int
    {
        return null;
    }

    /**
     * El proyecto que se va a editar. El id llega del navegador, así que quien
     * viva acotado a un cliente lo busca dentro de ese alcance.
     */
    protected function findProjectToEdit(int $projectId): Project
    {
        return Project::findOrFail($projectId);
    }

    protected function saveProject(CreateProjectFromTemplate $createProjectFromTemplate): Project
    {
        $project = $this->editingProjectId ? $this->findProjectToEdit($this->editingProjectId) : null;

        Gate::authorize($project ? 'update' : 'create', $project ?? Project::class);

        if (($lockedClientId = $this->lockedProjectClientId()) !== null) {
            $this->client_id = $lockedClientId;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'client_id' => ['required', 'exists:clients,id'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'type' => ['required', Rule::enum(ProjectType::class)],
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

        $this->modal('project-form')->close();

        Flux::toast(variant: 'success', text: $isNew ? __('Proyecto creado.') : __('Proyecto actualizado.'));

        return $project;
    }
}
