<?php

namespace Database\Factories;

use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceStatus;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
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
            'name' => fake()->randomElement(['Hosting', 'Dominio', 'Mantenimiento mensual', 'Ads', 'Desarrollo']),
            'description' => null,
            'billing_frequency' => ServiceBillingFrequency::Monthly,
            'amount' => fake()->randomFloat(2, 500, 5000),
            'currency' => 'MXN',
            'status' => ServiceStatus::Activo,
            'starts_on' => now()->toDateString(),
            'next_charge_date' => now()->toDateString(),
            'installments_count' => null,
        ];
    }

    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_frequency' => ServiceBillingFrequency::OneTime,
            'next_charge_date' => null,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_frequency' => ServiceBillingFrequency::Monthly,
            'next_charge_date' => $attributes['starts_on'] ?? now()->toDateString(),
        ]);
    }

    public function annual(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_frequency' => ServiceBillingFrequency::Annual,
            'next_charge_date' => $attributes['starts_on'] ?? now()->toDateString(),
        ]);
    }

    public function installment(int $count = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_frequency' => ServiceBillingFrequency::Installment,
            'next_charge_date' => null,
            'installments_count' => $count,
        ]);
    }
}
