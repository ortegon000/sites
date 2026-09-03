<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
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
            'service_id' => null,
            'name' => 'Cotización '.fake()->word().' '.fake()->word(),
            'description' => null,
            'category' => ServiceCategory::Other,
            'billing_frequency' => ServiceBillingFrequency::OneTime,
            'amount' => fake()->randomFloat(2, 1000, 40000),
            'currency' => 'MXN',
            'status' => QuoteStatus::Borrador,
            'valid_until' => now()->addDays(30)->toDateString(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Enviada,
            'sent_at' => now()->subDays(fake()->numberBetween(1, 10)),
        ]);
    }

    public function expiring(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Enviada,
            'sent_at' => now()->subDays(40),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
    }
}
