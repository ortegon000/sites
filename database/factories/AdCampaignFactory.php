<?php

namespace Database\Factories;

use App\Enums\AdBudgetBilling;
use App\Enums\AdCampaignStatus;
use App\Enums\AdPlatform;
use App\Models\AdCampaign;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdCampaign>
 */
class AdCampaignFactory extends Factory
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
            'name' => 'Campaña '.fake()->word().' '.fake()->word(),
            'platform' => AdPlatform::Meta,
            'ad_account_id' => (string) fake()->randomNumber(9, true),
            'objective' => fake()->randomElement(['Tráfico', 'Conversiones', 'Reconocimiento']),
            'monthly_budget' => fake()->randomFloat(2, 3000, 40000),
            'currency' => 'MXN',
            'budget_billing' => AdBudgetBilling::ClientDirect,
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
            'status' => AdCampaignStatus::Activa,
        ];
    }

    public function passThrough(): static
    {
        return $this->state(fn (array $attributes) => [
            'budget_billing' => AdBudgetBilling::PassThrough,
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AdCampaignStatus::Finalizada,
            'ends_on' => now()->toDateString(),
        ]);
    }
}
