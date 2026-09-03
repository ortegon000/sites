<?php

namespace App\Models;

use App\Enums\AdBudgetBilling;
use App\Enums\AdCampaignStatus;
use App\Enums\AdPlatform;
use Carbon\CarbonImmutable;
use Database\Factories\AdCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property AdPlatform $platform
 * @property string|null $ad_account_id
 * @property string|null $objective
 * @property string $monthly_budget
 * @property string $currency
 * @property AdBudgetBilling $budget_billing
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property AdCampaignStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['client_id', 'name', 'platform', 'ad_account_id', 'objective', 'monthly_budget', 'currency', 'budget_billing', 'starts_on', 'ends_on', 'status'])]
class AdCampaign extends Model
{
    /** @use HasFactory<AdCampaignFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'platform' => AdPlatform::class,
            'budget_billing' => AdBudgetBilling::class,
            'status' => AdCampaignStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The ad-spend service billed on behalf of this campaign, if the budget
     * passes through us. A campaign the client pays directly has none.
     *
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
