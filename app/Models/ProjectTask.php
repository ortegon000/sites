<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProjectTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lo que hay que hacer en un proyecto, que no es lo mismo que lo que se le
 * cobra: el servicio es la línea que se factura y la tarea es el trabajo. Una
 * tarea puede partirse en subtareas —un nivel, no un árbol— porque lo que se
 * necesita es "maquetar las cinco secciones", no un gestor de proyectos.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $parent_id
 * @property int|null $assigned_to_user_id
 * @property string $title
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['project_id', 'parent_id', 'assigned_to_user_id', 'title', 'due_date', 'completed_at'])]
class ProjectTask extends Model
{
    /** @use HasFactory<ProjectTaskFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_date !== null && $this->due_date->isBefore(today());
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
