<?php

namespace Database\Factories;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'amount' => fake()->randomFloat(2, 500, 5000),
            'currency' => 'MXN',
            'status' => ChargeStatus::Pendiente,
            'due_date' => now()->addDays(fake()->numberBetween(1, 15))->toDateString(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::Pendiente,
            'due_date' => now()->addDays(fake()->numberBetween(1, 15))->toDateString(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::Pagado,
            'due_date' => now()->subDays(fake()->numberBetween(1, 30))->toDateString(),
            'paid_at' => now()->subDays(fake()->numberBetween(0, 5)),
        ]);
    }

    /**
     * Vence dentro de la ventana de recordatorio de `charges:process`.
     */
    public function dueSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::Pendiente,
            'due_date' => now()->addDays(2)->toDateString(),
        ]);
    }

    /**
     * Su recordatorio ya salió, así que una corrida del comando lo deja en paz.
     */
    public function alreadyNotified(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_soon_notified_at' => now()->subDay(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::Vencido,
            'due_date' => now()->subDays(fake()->numberBetween(1, 20))->toDateString(),
        ]);
    }
}
