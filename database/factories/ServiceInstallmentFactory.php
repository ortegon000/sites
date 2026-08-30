<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceInstallment>
 */
class ServiceInstallmentFactory extends Factory
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
            'installment_number' => 1,
            'amount' => fake()->randomFloat(2, 500, 5000),
            'due_date' => now()->toDateString(),
        ];
    }
}
