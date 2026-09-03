<?php

use App\Livewire\TasksPanel;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('staff captura una tarea del proyecto con fecha y responsable', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $project->users()->attach($staff);

    $this->actingAs($staff);

    Livewire::test(TasksPanel::class, ['project' => $project])
        ->set('quickTitle', 'Maquetar la página de contacto')
        ->set('quickDueDate', today()->addWeek()->toDateString())
        ->set('quickAssignee', $staff->id)
        ->call('addTask')
        ->assertHasNoErrors()
        ->assertSee('Maquetar la página de contacto');

    $task = $project->tasks()->firstOrFail();

    expect($task->assigned_to_user_id)->toBe($staff->id)
        ->and($task->due_date->toDateString())->toBe(today()->addWeek()->toDateString())
        ->and($task->isDone())->toBeFalse();
});

test('solo se le puede encargar una tarea a quien está en el equipo del proyecto', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $ajeno = User::factory()->staff()->create();

    $this->actingAs($staff);

    Livewire::test(TasksPanel::class, ['project' => $project])
        ->set('quickTitle', 'Tarea')
        ->set('quickAssignee', $ajeno->id)
        ->call('addTask')
        ->assertHasErrors('quickAssignee');
});

test('una tarea se parte en subtareas', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $task = ProjectTask::factory()->for($project)->create(['title' => 'Sitio nuevo']);

    $this->actingAs($staff);

    Livewire::test(TasksPanel::class, ['project' => $project])
        ->call('startSubtask', $task->id)
        ->set('subtaskTitle', 'Maquetar inicio')
        ->call('addSubtask')
        ->assertHasNoErrors()
        ->assertSee('Maquetar inicio');

    expect($task->subtasks()->count())->toBe(1)
        ->and($project->tasks()->count())->toBe(1);
});

test('marcar la tarea hecha arrastra a sus subtareas, y desmarcarla las reabre', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $task = ProjectTask::factory()->for($project)->create();
    $subtask = ProjectTask::factory()->under($task)->create();

    $this->actingAs($staff);

    $panel = Livewire::test(TasksPanel::class, ['project' => $project])
        ->call('toggleTask', $task->id);

    expect($task->refresh()->isDone())->toBeTrue()
        ->and($subtask->refresh()->isDone())->toBeTrue();

    $panel->call('toggleTask', $task->id);

    expect($task->refresh()->isDone())->toBeFalse()
        ->and($subtask->refresh()->isDone())->toBeFalse();
});

test('agregarle una subtarea a una tarea ya cerrada la reabre', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $task = ProjectTask::factory()->for($project)->done()->create();

    $this->actingAs($staff);

    Livewire::test(TasksPanel::class, ['project' => $project])
        ->call('startSubtask', $task->id)
        ->set('subtaskTitle', 'Faltaba esto')
        ->call('addSubtask')
        ->assertHasNoErrors();

    expect($task->refresh()->isDone())->toBeFalse();
});

test('borrar una tarea se lleva sus subtareas', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $task = ProjectTask::factory()->for($project)->create();
    ProjectTask::factory()->under($task)->create();

    $this->actingAs($staff);

    Livewire::test(TasksPanel::class, ['project' => $project])
        ->call('deleteTask', $task->id);

    expect(ProjectTask::count())->toBe(0);
});

test('no se puede tocar la tarea de otro proyecto desde este panel', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $ajena = ProjectTask::factory()->create();

    $this->actingAs($staff);

    expect(fn () => Livewire::test(TasksPanel::class, ['project' => $project])->call('deleteTask', $ajena->id))
        ->toThrow(ModelNotFoundException::class);
});

test('el colaborador marca lo que trae asignado, pero no captura ni borra', function () {
    $collaborator = User::factory()->collaborator()->create();
    $project = Project::factory()->create();
    $project->users()->attach($collaborator);

    $suya = ProjectTask::factory()->for($project)->create(['assigned_to_user_id' => $collaborator->id]);
    $ajena = ProjectTask::factory()->for($project)->create();

    $this->actingAs($collaborator);

    Livewire::test(TasksPanel::class, ['project' => $project])
        ->assertSet('canManageTasks', false)
        ->call('toggleTask', $suya->id);

    expect($suya->refresh()->isDone())->toBeTrue();

    Livewire::test(TasksPanel::class, ['project' => $project])
        ->call('toggleTask', $ajena->id)
        ->assertForbidden();

    Livewire::test(TasksPanel::class, ['project' => $project])
        ->set('quickTitle', 'No debería poder')
        ->call('addTask')
        ->assertForbidden();
});
