<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\ChargePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargePayment>
 */
class ChargePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'charge_id' => Charge::factory(),
            'amount' => fake()->randomFloat(2, 100, 2000),
            'paid_on' => now()->subDays(fake()->numberBetween(0, 20))->toDateString(),
            'method' => fake()->randomElement(['Transferencia', 'Efectivo', 'Depósito']),
            'account' => fake()->randomElement(['BBVA', 'Banorte', null]),
            'reference' => fake()->optional()->bothify('REF-####'),
            'invoice_reference' => fake()->optional()->bothify('A-####'),
        ];
    }
}
