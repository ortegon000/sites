<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ClientType::Prospect,
            'status' => ClientStatus::Nuevo,
            'name' => fake()->company(),
            'company_name' => fake()->company().' S.A. de C.V.',
            'source' => fake()->randomElement(['referido', 'google', 'redes_sociales', 'evento']),
            'currency' => 'MXN',
        ];
    }

    public function prospect(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ClientType::Prospect,
            'status' => ClientStatus::Nuevo,
        ]);
    }

    /**
     * Le cuelga una persona de contacto, marcada como principal.
     */
    public function withContact(?Contact $contact = null): static
    {
        return $this->hasAttached(
            $contact ?? Contact::factory(),
            ['is_primary' => true],
            'contacts',
        );
    }

    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ClientType::Client,
            'status' => ClientStatus::Activo,
            'won_at' => now(),
        ]);
    }
}
