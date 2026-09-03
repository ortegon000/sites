<?php

use App\Enums\AdBudgetBilling;
use App\Enums\AdCampaignStatus;
use App\Enums\AdPlatform;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Livewire\CampaignsPanel;
use App\Models\AdCampaign;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('staff can add a campaign the client pays directly, and it generates no charges', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(CampaignsPanel::class, ['client' => $client])
        ->call('openCampaignModal')
        ->set('campaignName', 'Meta — temporada alta')
        ->set('platform', AdPlatform::Meta->value)
        ->set('monthlyBudget', '30000')
        ->set('budgetBilling', AdBudgetBilling::ClientDirect->value)
        ->call('saveCampaign')
        ->assertHasNoErrors();

    $campaign = $client->adCampaigns()->firstOrFail();

    expect($campaign->budget_billing)->toBe(AdBudgetBilling::ClientDirect)
        ->and($campaign->services)->toHaveCount(0)
        ->and($client->services()->count())->toBe(0);
});

test('a pass-through campaign creates its own monthly ad spend service', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(CampaignsPanel::class, ['client' => $client])
        ->call('openCampaignModal')
        ->set('campaignName', 'Google — siempre activa')
        ->set('platform', AdPlatform::Google->value)
        ->set('monthlyBudget', '18000')
        ->set('budgetBilling', AdBudgetBilling::PassThrough->value)
        ->call('saveCampaign')
        ->assertHasNoErrors();

    $campaign = $client->adCampaigns()->firstOrFail();
    $service = $client->services()->firstOrFail();

    expect($service->ad_campaign_id)->toBe($campaign->id)
        ->and($service->project_id)->toBeNull()
        ->and($service->category)->toBe(ServiceCategory::AdsBudget)
        ->and($service->billing_frequency)->toBe(ServiceBillingFrequency::Monthly)
        ->and((float) $service->amount)->toBe(18000.0)
        ->and($service->charges()->count())->toBe(1);
});

test('a pass-through campaign can skip the ad spend service', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(CampaignsPanel::class, ['client' => $client])
        ->call('openCampaignModal')
        ->set('campaignName', 'Meta — facturada aparte')
        ->set('monthlyBudget', '5000')
        ->set('budgetBilling', AdBudgetBilling::PassThrough->value)
        ->set('createBudgetService', false)
        ->call('saveCampaign')
        ->assertHasNoErrors();

    expect($client->services()->count())->toBe(0);
});

test('a campaign cannot end before it starts', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(CampaignsPanel::class, ['client' => $client])
        ->call('openCampaignModal')
        ->set('campaignName', 'Campaña inválida')
        ->set('monthlyBudget', '1000')
        ->set('campaignStartsOn', now()->toDateString())
        ->set('campaignEndsOn', now()->subMonth()->toDateString())
        ->call('saveCampaign')
        ->assertHasErrors('campaignEndsOn');

    expect($client->adCampaigns()->count())->toBe(0);
});

test('editing a campaign does not create another ad spend service', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $campaign = AdCampaign::factory()->for($client)->passThrough()->create();

    $this->actingAs($staff);

    Livewire::test(CampaignsPanel::class, ['client' => $client])
        ->call('openCampaignModal', $campaign->id)
        ->set('campaignStatus', AdCampaignStatus::Pausada->value)
        ->call('saveCampaign')
        ->assertHasNoErrors();

    expect($campaign->refresh()->status)->toBe(AdCampaignStatus::Pausada)
        ->and($client->services()->count())->toBe(0);
});

test('el detalle del proyecto ya no muestra campañas ni dominios: viven en la ficha del cliente', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();

    AdCampaign::factory()->for($client)->create(['name' => 'Meta — remarketing']);

    $this->actingAs($staff);

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSee('Campañas de ads')
        ->assertDontSee('Meta — remarketing')
        ->assertDontSee('Dominios y correo');
});

test('las campañas del cliente se administran desde su ficha', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    AdCampaign::factory()->for($client)->create(['name' => 'Meta — remarketing']);
    AdCampaign::factory()->for($client)->create(['name' => 'Google — marca']);
    AdCampaign::factory()->create(['name' => 'Campaña ajena']);

    $this->actingAs($staff);

    Livewire::test(CampaignsPanel::class, ['client' => $client])
        ->assertSee('Meta — remarketing')
        ->assertSee('Google — marca')
        ->assertDontSee('Campaña ajena');
});
