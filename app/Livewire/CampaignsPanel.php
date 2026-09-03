<?php

namespace App\Livewire;

use App\Actions\Services\CreateServiceWithSchedule;
use App\Enums\AdBudgetBilling;
use App\Enums\AdCampaignStatus;
use App\Enums\AdPlatform;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Models\AdCampaign;
use App\Models\Client;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Las campañas de ads de un cliente. Un cliente puede correr varias a la vez
 * (Meta y Google en paralelo), y cada una decide por su cuenta si la inversión
 * se factura a través nuestro o la paga el cliente directo a la plataforma.
 *
 * La campaña es un activo del cliente, no de un proyecto: el montaje inicial
 * fue un trabajo que terminó, y la campaña le sobrevive. Por eso cuelga del
 * cliente y se administra desde su ficha.
 */
class CampaignsPanel extends Component
{
    public Client $client;

    public ?int $editingCampaignId = null;

    public string $campaignName = '';

    public string $platform = '';

    public ?string $adAccountId = null;

    public ?string $objective = null;

    public string $monthlyBudget = '';

    public string $currency = 'MXN';

    public string $budgetBilling = '';

    public ?string $campaignStartsOn = null;

    public ?string $campaignEndsOn = null;

    public string $campaignStatus = '';

    /**
     * Whether to also create the recurring ad-spend service for a campaign we
     * bill. It is a choice rather than an automatism because some campaigns are
     * invoiced outside the CRM, and a surprise service means surprise charges.
     */
    public bool $createBudgetService = true;

    public function mount(Client $client): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
        $this->currency = $client->currency;
    }

    /**
     * @return Collection<int, AdCampaign>
     */
    #[Computed]
    public function campaigns(): Collection
    {
        return $this->campaignsQuery()
            ->with(['services'])
            ->orderByDesc('starts_on')
            ->get();
    }

    /**
     * @return array<int, AdPlatform>
     */
    #[Computed]
    public function platformOptions(): array
    {
        return AdPlatform::cases();
    }

    /**
     * @return array<int, AdBudgetBilling>
     */
    #[Computed]
    public function budgetBillingOptions(): array
    {
        return AdBudgetBilling::cases();
    }

    /**
     * @return array<int, AdCampaignStatus>
     */
    #[Computed]
    public function campaignStatusOptions(): array
    {
        return AdCampaignStatus::cases();
    }

    public function openCampaignModal(?int $campaignId = null): void
    {
        Gate::authorize('update', $this->client);

        $this->resetValidation();
        $this->editingCampaignId = $campaignId;
        $this->createBudgetService = $campaignId === null;

        if ($campaignId === null) {
            $this->campaignName = '';
            $this->platform = AdPlatform::Meta->value;
            $this->adAccountId = null;
            $this->objective = null;
            $this->monthlyBudget = '';
            $this->currency = $this->client->currency;
            $this->budgetBilling = AdBudgetBilling::ClientDirect->value;
            $this->campaignStartsOn = today()->toDateString();
            $this->campaignEndsOn = null;
            $this->campaignStatus = AdCampaignStatus::Activa->value;
        } else {
            $campaign = $this->findCampaign($campaignId);

            $this->campaignName = $campaign->name;
            $this->platform = $campaign->platform->value;
            $this->adAccountId = $campaign->ad_account_id;
            $this->objective = $campaign->objective;
            $this->monthlyBudget = $campaign->monthly_budget;
            $this->currency = $campaign->currency;
            $this->budgetBilling = $campaign->budget_billing->value;
            $this->campaignStartsOn = $campaign->starts_on?->toDateString();
            $this->campaignEndsOn = $campaign->ends_on?->toDateString();
            $this->campaignStatus = $campaign->status->value;
        }

        $this->modal('campaign-form')->show();
    }

    public function saveCampaign(CreateServiceWithSchedule $createServiceWithSchedule): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'campaignName' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::enum(AdPlatform::class)],
            'adAccountId' => ['nullable', 'string', 'max:255'],
            'objective' => ['nullable', 'string', 'max:255'],
            'monthlyBudget' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'budgetBilling' => ['required', Rule::enum(AdBudgetBilling::class)],
            'campaignStartsOn' => ['required', 'date'],
            'campaignEndsOn' => ['nullable', 'date', 'after_or_equal:campaignStartsOn'],
            'campaignStatus' => ['required', Rule::enum(AdCampaignStatus::class)],
        ]);

        $budgetBilling = AdBudgetBilling::from($validated['budgetBilling']);

        $attributes = [
            'name' => $validated['campaignName'],
            'platform' => AdPlatform::from($validated['platform']),
            'ad_account_id' => $validated['adAccountId'],
            'objective' => $validated['objective'],
            'monthly_budget' => $validated['monthlyBudget'],
            'currency' => $validated['currency'],
            'budget_billing' => $budgetBilling,
            'starts_on' => $validated['campaignStartsOn'],
            'ends_on' => $validated['campaignEndsOn'],
            'status' => AdCampaignStatus::from($validated['campaignStatus']),
        ];

        if ($this->editingCampaignId === null) {
            $campaign = $this->client->adCampaigns()->create($attributes);

            if ($budgetBilling->isBilledByUs() && $this->createBudgetService) {
                $createServiceWithSchedule->handle($this->client, [
                    'ad_campaign_id' => $campaign->id,
                    'name' => __('Inversión publicitaria').' · '.$campaign->name,
                    'description' => null,
                    'category' => ServiceCategory::AdsBudget,
                    'billing_frequency' => ServiceBillingFrequency::Monthly,
                    'amount' => $campaign->monthly_budget,
                    'currency' => $campaign->currency,
                    'status' => ServiceStatus::Activo,
                    'starts_on' => $campaign->starts_on->toDateString(),
                    'installments_count' => null,
                ]);
            }
        } else {
            $this->findCampaign($this->editingCampaignId)->update($attributes);
        }

        unset($this->campaigns);

        $this->modal('campaign-form')->close();

        Flux::toast(variant: 'success', text: __('Campaña guardada.'));
    }

    public function closeCampaignModal(): void
    {
        $this->modal('campaign-form')->close();
    }

    public function deleteCampaign(int $campaignId): void
    {
        Gate::authorize('update', $this->client);

        $this->findCampaign($campaignId)->delete();

        unset($this->campaigns);

        Flux::toast(variant: 'success', text: __('Campaña eliminada.'));
    }

    /**
     * @return Builder<AdCampaign>
     */
    private function campaignsQuery(): Builder
    {
        return AdCampaign::query()->where('client_id', $this->client->id);
    }

    private function findCampaign(int $campaignId): AdCampaign
    {
        return $this->campaignsQuery()->findOrFail($campaignId);
    }

    public function render(): View
    {
        return view('livewire.campaigns-panel');
    }
}
