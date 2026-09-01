<?php

use App\Enums\AdBudgetBilling;
use App\Enums\AdCampaignStatus;
use App\Enums\AdPlatform;
use App\Enums\ProjectType;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Livewire\ProjectCampaigns;
use App\Models\AdCampaign;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('staff can add a campaign the client pays directly, and it generates no charges', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create(['type' => ProjectType::Ads]);

    $this->actingAs($staff);

    Livewire::test(ProjectCampaigns::class, ['project' => $project])
        ->call('openCampaignModal')
        ->set('campaignName', 'Meta — temporada alta')
        ->set('platform', AdPlatform::Meta->value)
        ->set('monthlyBudget', '30000')
        ->set('budgetBilling', AdBudgetBilling::ClientDirect->value)
        ->call('saveCampaign')
        ->assertHasNoErrors();

    $campaign = $project->adCampaigns()->firstOrFail();

    expect($campaign->budget_billing)->toBe(AdBudgetBilling::ClientDirect)
        ->and($campaign->services)->toHaveCount(0)
        ->and($project->services()->count())->toBe(0);
});

test('a pass-through campaign creates its own monthly ad spend service', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create(['type' => ProjectType::Ads]);

    $this->actingAs($staff);

    Livewire::test(ProjectCampaigns::class, ['project' => $project])
        ->call('openCampaignModal')
        ->set('campaignName', 'Google — siempre activa')
        ->set('platform', AdPlatform::Google->value)
        ->set('monthlyBudget', '18000')
        ->set('budgetBilling', AdBudgetBilling::PassThrough->value)
        ->call('saveCampaign')
        ->assertHasNoErrors();

    $campaign = $project->adCampaigns()->firstOrFail();
    $service = $project->services()->firstOrFail();

    expect($service->ad_campaign_id)->toBe($campaign->id)
        ->and($service->category)->toBe(ServiceCategory::AdsBudget)
        ->and($service->billing_frequency)->toBe(ServiceBillingFrequency::Monthly)
        ->and((float) $service->amount)->toBe(18000.0)
        ->and($service->charges()->count())->toBe(1);
});

test('a pass-through campaign can skip the ad spend service', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create(['type' => ProjectType::Ads]);

    $this->actingAs($staff);

    Livewire::test(ProjectCampaigns::class, ['project' => $project])
        ->call('openCampaignModal')
        ->set('campaignName', 'Meta — facturada aparte')
        ->set('monthlyBudget', '5000')
        ->set('budgetBilling', AdBudgetBilling::PassThrough->value)
        ->set('createBudgetService', false)
        ->call('saveCampaign')
        ->assertHasNoErrors();

    expect($project->services()->count())->toBe(0);
});

test('a campaign cannot end before it starts', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();

    $this->actingAs($staff);

    Livewire::test(ProjectCampaigns::class, ['project' => $project])
        ->call('openCampaignModal')
        ->set('campaignName', 'Campaña inválida')
        ->set('monthlyBudget', '1000')
        ->set('campaignStartsOn', now()->toDateString())
        ->set('campaignEndsOn', now()->subMonth()->toDateString())
        ->call('saveCampaign')
        ->assertHasErrors('campaignEndsOn');

    expect($project->adCampaigns()->count())->toBe(0);
});

test('editing a campaign does not create another ad spend service', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $campaign = AdCampaign::factory()->for($project)->passThrough()->create();

    $this->actingAs($staff);

    Livewire::test(ProjectCampaigns::class, ['project' => $project])
        ->call('openCampaignModal', $campaign->id)
        ->set('campaignStatus', AdCampaignStatus::Pausada->value)
        ->call('saveCampaign')
        ->assertHasNoErrors();

    expect($campaign->refresh()->status)->toBe(AdCampaignStatus::Pausada)
        ->and($project->services()->count())->toBe(0);
});

test('collaborator does not see the campaigns card on a project', function () {
    $collaborator = User::factory()->collaborator()->create();
    $project = Project::factory()->create();
    $project->users()->attach($collaborator);
    AdCampaign::factory()->for($project)->create(['name' => 'Campaña reservada']);

    $this->actingAs($collaborator);

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSee('Campañas de ads')
        ->assertDontSee('Campaña reservada');
});
