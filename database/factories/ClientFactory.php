<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ClientType::Prospect,
            'status' => ClientStatus::Nuevo,
            'name' => fake()->name(),
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'source' => fake()->randomElement(['referido', 'google', 'redes_sociales', 'evento']),
            'currency' => 'MXN',
        ];
    }

    public function prospect(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ClientType::Prospect,
            'status' => ClientStatus::Nuevo,
        ]);
    }

    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ClientType::Client,
            'status' => ClientStatus::Activo,
            'won_at' => now(),
        ]);
    }
}
