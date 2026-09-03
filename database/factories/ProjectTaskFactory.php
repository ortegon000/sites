<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTask>
 */
class ProjectTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'assigned_to_user_id' => null,
            'title' => fake()->sentence(4),
            'due_date' => now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'completed_at' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now()->subDays(fake()->numberBetween(1, 10)),
        ]);
    }

    /**
     * Una subtarea de la tarea que se le pase.
     */
    public function under(ProjectTask $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $parent->project_id,
            'parent_id' => $parent->id,
        ]);
    }
}
