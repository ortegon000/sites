<?php

namespace Database\Factories;

use App\Enums\ClientNoteType;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientNote>
 */
class ClientNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'user_id' => User::factory(),
            'type' => ClientNoteType::Note,
            'body' => fake()->sentence(12),
        ];
    }

    public function call(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ClientNoteType::Call,
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ClientNoteType::Email,
        ]);
    }

    public function statusChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ClientNoteType::StatusChange,
            'body' => 'Estatus actualizado.',
        ]);
    }
}
