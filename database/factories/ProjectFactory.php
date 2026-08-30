<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory()->client(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'status' => ProjectStatus::Activo,
            'started_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
