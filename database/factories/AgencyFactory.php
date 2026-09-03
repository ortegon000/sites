<?php

namespace Database\Factories;

use App\Enums\AgencyBillingTarget;
use App\Enums\AgencyStatus;
use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'billing_target' => AgencyBillingTarget::Client,
            'status' => AgencyStatus::Activa,
        ];
    }
}
