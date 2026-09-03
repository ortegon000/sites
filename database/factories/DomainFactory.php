<?php

namespace Database\Factories;

use App\Enums\DomainEmailManagement;
use App\Enums\DomainManagement;
use App\Enums\DomainStatus;
use App\Models\Client;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
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
            'name' => fake()->unique()->domainName(),
            'management' => DomainManagement::Managed,
            'registrar' => fake()->randomElement(['Namecheap', 'GoDaddy', 'Cloudflare']),
            'registered_at' => fake()->dateTimeBetween('-3 years', '-1 year'),
            'expires_at' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'auto_renew' => true,
            'email_management' => DomainEmailManagement::NotManaged,
            'status' => DomainStatus::Activo,
        ];
    }

    public function tracked(): static
    {
        return $this->state(fn (array $attributes) => [
            'management' => DomainManagement::Tracked,
            'registrar' => null,
            'auto_renew' => false,
        ]);
    }

    public function withManagedEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_management' => DomainEmailManagement::Managed,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DomainStatus::Expirado,
            'expires_at' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }
}
