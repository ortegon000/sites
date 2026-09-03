<?php

namespace Database\Factories;

use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Models\Client;
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
            /** El cliente se hereda del proyecto cuando hay uno, para que la
             *  línea no termine colgando de un cliente distinto al del proyecto. */
            'client_id' => fn (array $attributes) => isset($attributes['project_id'])
                ? Project::query()->whereKey($attributes['project_id'])->value('client_id')
                : Client::factory(),
            'domain_id' => null,
            'ad_campaign_id' => null,
            'name' => fake()->randomElement(['Hosting', 'Dominio', 'Mantenimiento mensual', 'Ads', 'Desarrollo']),
            'description' => null,
            'category' => ServiceCategory::Other,
            'billing_frequency' => ServiceBillingFrequency::Monthly,
            'amount' => fake()->randomFloat(2, 500, 5000),
            'currency' => 'MXN',
            'status' => ServiceStatus::Activo,
            'starts_on' => now()->toDateString(),
            'next_charge_date' => now()->toDateString(),
            'installments_count' => null,
        ];
    }

    /**
     * Una línea suelta: cuelga del cliente sin pasar por ningún proyecto.
     */
    public function standalone(): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => null,
        ]);
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

    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_frequency' => ServiceBillingFrequency::Quarterly,
            'next_charge_date' => $attributes['starts_on'] ?? now()->toDateString(),
        ]);
    }

    public function semiannual(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_frequency' => ServiceBillingFrequency::Semiannual,
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

    public function biweekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_frequency' => ServiceBillingFrequency::Biweekly,
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
