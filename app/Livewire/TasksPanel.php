<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Las tareas de un proyecto, con un nivel de subtareas.
 *
 * Es la otra mitad del proyecto: el panel de servicios dice qué se le cobra al
 * cliente y este dice qué hay que hacer. Se capturan en un renglón, como las
 * líneas cobrables, porque una lista de pendientes que cuesta llenar se acaba
 * llevando en otro lado.
 *
 * Vive solo en el detalle del proyecto: una tarea suelta de un cliente sin
 * proyecto no tiene dónde vivir todavía, y no vale la pena inventárselo.
 */
class TasksPanel extends Component
{
    public Project $project;

    public string $quickTitle = '';

    public ?string $quickDueDate = null;

    public ?int $quickAssignee = null;

    /** La tarea a la que se le está agregando una subtarea, si hay alguna. */
    public ?int $addingSubtaskFor = null;

    public string $subtaskTitle = '';

    public ?int $editingTaskId = null;

    public string $taskTitle = '';

    public ?string $taskDueDate = null;

    public ?int $taskAssignee = null;

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->project = $project;
    }

    /**
     * @return Collection<int, ProjectTask>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return $this->tasksQuery()
            ->whereNull('parent_id')
            ->with([
                'assignee',
                'subtasks' => fn ($query) => $query->with('assignee')->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id'),
            ])
            ->orderByRaw('completed_at is not null')
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * A quién se le puede encargar: el equipo asignado al proyecto, que es
     * justamente para lo que se asigna.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignableUsers(): Collection
    {
        return $this->project->users()->orderBy('name')->get();
    }

    /**
     * Quien puede administrar las tareas es quien puede tocar el proyecto. Un
     * colaborador solo las ve —y marca las suyas—, no las captura ni las borra.
     */
    #[Computed]
    public function canManageTasks(): bool
    {
        return Gate::allows('update', $this->project);
    }

    public function addTask(): void
    {
        Gate::authorize('update', $this->project);

        $validated = $this->validate([
            'quickTitle' => ['required', 'string', 'max:255'],
            'quickDueDate' => ['nullable', 'date'],
            'quickAssignee' => ['nullable', $this->assigneeRule()],
        ]);

        $this->project->tasks()->create([
            'title' => $validated['quickTitle'],
            'due_date' => $validated['quickDueDate'],
            'assigned_to_user_id' => $validated['quickAssignee'],
        ]);

        $this->reset(['quickTitle', 'quickDueDate', 'quickAssignee']);
        unset($this->tasks);

        Flux::toast(variant: 'success', text: __('Tarea agregada.'));
    }

    public function startSubtask(int $taskId): void
    {
        Gate::authorize('update', $this->project);

        $this->addingSubtaskFor = $this->findTask($taskId)->id;
        $this->subtaskTitle = '';
        $this->resetValidation();
    }

    public function cancelSubtask(): void
    {
        $this->reset(['addingSubtaskFor', 'subtaskTitle']);
        $this->resetValidation();
    }

    public function addSubtask(): void
    {
        Gate::authorize('update', $this->project);

        $parent = $this->findTask($this->addingSubtaskFor ?? 0);

        $validated = $this->validate([
            'subtaskTitle' => ['required', 'string', 'max:255'],
        ]);

        $parent->subtasks()->create([
            'project_id' => $this->project->id,
            'title' => $validated['subtaskTitle'],
        ]);

        /** Agregarle trabajo a una tarea ya cerrada la reabre: falta algo por hacer. */
        if ($parent->isDone()) {
            $parent->update(['completed_at' => null]);
        }

        $this->reset(['addingSubtaskFor', 'subtaskTitle']);
        unset($this->tasks);
    }

    /**
     * Marcar una tarea arrastra a sus subtareas: cerrar la madre dejando
     * pendientes abajo deja la lista diciendo dos cosas distintas.
     */
    public function toggleTask(int $taskId): void
    {
        $task = $this->findTask($taskId);

        $this->authorizeToggle($task);

        $done = ! $task->isDone();

        $task->update(['completed_at' => $done ? now() : null]);
        $task->subtasks()->update(['completed_at' => $done ? now() : null]);

        unset($this->tasks);
    }

    public function openTaskModal(int $taskId): void
    {
        Gate::authorize('update', $this->project);

        $task = $this->findTask($taskId);

        $this->editingTaskId = $task->id;
        $this->taskTitle = $task->title;
        $this->taskDueDate = $task->due_date?->toDateString();
        $this->taskAssignee = $task->assigned_to_user_id;
        $this->resetValidation();

        $this->modal('project-task-form')->show();
    }

    public function saveTask(): void
    {
        Gate::authorize('update', $this->project);

        $task = $this->findTask($this->editingTaskId ?? 0);

        $validated = $this->validate([
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskDueDate' => ['nullable', 'date'],
            'taskAssignee' => ['nullable', $this->assigneeRule()],
        ]);

        $task->update([
            'title' => $validated['taskTitle'],
            'due_date' => $validated['taskDueDate'],
            'assigned_to_user_id' => $validated['taskAssignee'],
        ]);

        unset($this->tasks);

        $this->modal('project-task-form')->close();

        Flux::toast(variant: 'success', text: __('Tarea actualizada.'));
    }

    public function closeTaskModal(): void
    {
        $this->modal('project-task-form')->close();
    }

    public function deleteTask(int $taskId): void
    {
        Gate::authorize('update', $this->project);

        $this->findTask($taskId)->delete();

        unset($this->tasks);

        Flux::toast(variant: 'success', text: __('Tarea eliminada.'));
    }

    /**
     * Solo se le puede encargar algo a quien está asignado al proyecto, y el
     * id llega del navegador.
     */
    private function assigneeRule(): Exists
    {
        return Rule::exists('project_user', 'user_id')->where('project_id', $this->project->id);
    }

    /**
     * El colaborador asignado marca lo suyo: es su única forma de reportar
     * avance, porque no entra a la ficha del cliente ni al listado.
     */
    private function authorizeToggle(ProjectTask $task): void
    {
        if (Gate::allows('update', $this->project)) {
            return;
        }

        abort_unless($task->assigned_to_user_id === auth()->id(), 403);
    }

    /**
     * @return Builder<ProjectTask>
     */
    private function tasksQuery(): Builder
    {
        return ProjectTask::query()->where('project_id', $this->project->id);
    }

    /**
     * El id llega del navegador, así que se busca dentro de este proyecto.
     */
    private function findTask(int $taskId): ProjectTask
    {
        return $this->tasksQuery()->findOrFail($taskId);
    }

    public function render(): View
    {
        return view('livewire.tasks-panel');
    }
}
