<?php

namespace Database\Factories;

use App\Enums\EmailAccountOrigin;
use App\Enums\EmailAccountStatus;
use App\Models\Domain;
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
            'domain_id' => Domain::factory()->withManagedEmail(),
            'email_provider_id' => EmailProvider::factory(),
            'email_address' => fake()->unique()->userName().'@'.fake()->domainName(),
            'password' => null,
            'origin' => EmailAccountOrigin::Provisioned,
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

    public function imported(): static
    {
        return $this->state(fn (array $attributes) => [
            'origin' => EmailAccountOrigin::Imported,
            'provisioned_at' => null,
        ]);
    }
}
