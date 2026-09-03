<?php

namespace Database\Factories;

use App\Enums\DomainCredentialKind;
use App\Models\Domain;
use App\Models\DomainCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DomainCredential>
 */
class DomainCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'kind' => DomainCredentialKind::Panel,
            'label' => null,
            'url' => 'https://cpanel.'.fake()->domainName(),
            'username' => fake()->userName(),
            'password' => fake()->password(12, 16),
            'notes' => null,
        ];
    }

    public function database(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => DomainCredentialKind::Database,
            'url' => null,
            'label' => fake()->userName().'_app',
        ]);
    }

    public function cms(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => DomainCredentialKind::Cms,
            'label' => 'WordPress',
        ]);
    }
}
