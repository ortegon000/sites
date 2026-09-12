<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteLineItem;
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
            'is_project' => false,
            'name' => 'Cotización '.fake()->word().' '.fake()->word(),
            'description' => null,
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

    /**
     * Lo cotizado es un trabajo completo, así que al aceptarse abre proyecto.
     */
    public function asProject(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_project' => true,
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

    /**
     * Agrega un renglón a la cotización. Encadenable para armar propuestas de
     * varios conceptos.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withLineItem(array $attributes = []): static
    {
        return $this->has(QuoteLineItem::factory()->state($attributes), 'lineItems');
    }
}
