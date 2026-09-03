<?php

namespace App\Livewire;

use App\Actions\Projects\CreateProjectFromTemplate;
use App\Concerns\ManagesProjectForm;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Los proyectos de un cliente, con lo que cuelga de cada uno.
 *
 * Vive en la ficha del cliente porque es su expediente: antes había que ir al
 * listado general y filtrar para ver los suyos, y dar de alta un proyecto
 * mandaba a esa otra pantalla. Aquí se captura sin salir de la ficha, con el
 * mismo formulario del listado y el cliente ya resuelto.
 */
class ProjectsPanel extends Component
{
    use ManagesProjectForm;

    public Client $client;

    public function mount(Client $client): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return $this->client->projects()
            ->withCount('services')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();
    }

    public function save(CreateProjectFromTemplate $createProjectFromTemplate): void
    {
        $this->saveProject($createProjectFromTemplate);

        unset($this->projects);
    }

    /**
     * Aceptar una cotización puede abrir un proyecto, y la ficha avisa para
     * que la tabla lo muestre sin recargar.
     */
    #[On('quote-accepted')]
    public function refreshProjects(): void
    {
        unset($this->projects);
    }

    protected function lockedProjectClientId(): ?int
    {
        return $this->client->id;
    }

    protected function findProjectToEdit(int $projectId): Project
    {
        return $this->client->projects()->findOrFail($projectId);
    }

    public function render(): View
    {
        return view('livewire.projects-panel');
    }
}
