<?php

namespace Database\Factories;

use App\Enums\RenewalStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Renewal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Renewal>
 */
class RenewalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = Domain::factory();

        return [
            'renewable_type' => Domain::class,
            'renewable_id' => $domain,
            'client_id' => fn (array $attributes) => Domain::query()->whereKey($attributes['renewable_id'])->value('client_id') ?? Client::factory(),
            'due_date' => now()->addDays(fake()->numberBetween(5, 45))->toDateString(),
            'status' => RenewalStatus::PorAvisar,
            'amount' => fake()->randomFloat(2, 500, 6000),
            'currency' => 'MXN',
        ];
    }

    public function notified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RenewalStatus::Avisado,
            'notified_at' => now()->subDays(2),
        ]);
    }
}
