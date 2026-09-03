<?php

namespace Database\Factories;

use App\Enums\LicenseStatus;
use App\Models\Client;
use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
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
            'domain_id' => null,
            'name' => fake()->randomElement(['Brevo', 'Elementor Pro', 'WP Rocket', 'WhatsApp Business API']),
            'vendor' => fake()->company(),
            'url' => null,
            'username' => null,
            'password' => null,
            'cost' => fake()->randomFloat(2, 500, 6000),
            'currency' => 'MXN',
            'renewal_date' => now()->addMonths(fake()->numberBetween(1, 11))->toDateString(),
            'auto_renew' => false,
            'status' => LicenseStatus::Activa,
            'notes' => null,
        ];
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'renewal_date' => now()->addDays(10)->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LicenseStatus::Cancelada,
        ]);
    }
}
