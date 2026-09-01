<?php

namespace Database\Seeders;

use App\Enums\AdBudgetBilling;
use App\Enums\AdCampaignStatus;
use App\Enums\AdPlatform;
use App\Enums\ProjectType;
use App\Models\AdCampaign;
use App\Models\Project;
use Illuminate\Database\Seeder;

class AdCampaignSeeder extends Seeder
{
    /**
     * Turn the last seeded project into an ads project with one campaign of
     * each billing kind, so both branches — budget billed through us and budget
     * paid straight to the platform — are visible in the UI.
     */
    public function run(): void
    {
        $project = Project::latest('id')->first();

        if ($project === null) {
            return;
        }

        $project->update(['type' => ProjectType::Ads]);

        AdCampaign::factory()->for($project)->passThrough()->create([
            'name' => 'Meta — remarketing',
            'platform' => AdPlatform::Meta,
            'objective' => 'Conversiones',
            'monthly_budget' => '18000.00',
            'budget_billing' => AdBudgetBilling::PassThrough,
            'status' => AdCampaignStatus::Activa,
        ]);

        AdCampaign::factory()->for($project)->create([
            'name' => 'Google — búsqueda de marca',
            'platform' => AdPlatform::Google,
            'objective' => 'Tráfico',
            'monthly_budget' => '9000.00',
            'budget_billing' => AdBudgetBilling::ClientDirect,
            'status' => AdCampaignStatus::Activa,
        ]);
    }
}
