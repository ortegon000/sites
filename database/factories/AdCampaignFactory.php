<?php

namespace Database\Factories;

use App\Enums\AdBudgetBilling;
use App\Enums\AdCampaignStatus;
use App\Enums\AdPlatform;
use App\Models\AdCampaign;
use App\Models\Client;
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
            'client_id' => fn (array $attributes) => isset($attributes['project_id'])
                ? Project::query()->whereKey($attributes['project_id'])->value('client_id')
                : Client::factory(),
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

    /**
     * Una campaña que no nació de ningún proyecto: cuelga solo del cliente.
     */
    public function standalone(): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => null,
        ]);
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
