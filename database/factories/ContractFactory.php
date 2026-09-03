<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
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
            'project_id' => null,
            'quote_id' => null,
            'number' => fn () => Contract::nextNumber(),
            'title' => 'Contrato de prestación de servicios',
            'status' => ContractStatus::Borrador,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'currency' => 'MXN',
            'body' => 'Cuerpo del contrato.',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContractStatus::Enviado,
            'sent_at' => now()->subDays(3),
        ]);
    }

    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContractStatus::Firmado,
            'sent_at' => now()->subDays(10),
            'signed_at' => now()->subDays(5),
            'signed_by' => fake()->name(),
        ]);
    }
}
