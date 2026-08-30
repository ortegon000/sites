<?php

namespace Database\Factories;

use App\Enums\EmailAccountStatus;
use App\Models\Client;
use App\Models\EmailAccount;
use App\Models\EmailProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAccount>
 */
class EmailAccountFactory extends Factory
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
            'email_provider_id' => EmailProvider::factory(),
            'email_address' => fake()->unique()->userName().'@'.fake()->domainName(),
            'status' => EmailAccountStatus::Activa,
            'provisioned_at' => now(),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmailAccountStatus::Suspendida,
        ]);
    }
}
