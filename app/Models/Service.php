<?php

namespace App\Models;

use App\Enums\ChargeStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $domain_id
 * @property int|null $ad_campaign_id
 * @property string $name
 * @property string|null $description
 * @property ServiceCategory $category
 * @property ServiceBillingFrequency $billing_frequency
 * @property string $amount
 * @property string $currency
 * @property ServiceStatus $status
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $next_charge_date
 * @property int|null $installments_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['project_id', 'domain_id', 'ad_campaign_id', 'name', 'description', 'category', 'billing_frequency', 'amount', 'currency', 'status', 'starts_on', 'next_charge_date', 'installments_count'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'billing_frequency' => ServiceBillingFrequency::class,
            'category' => ServiceCategory::class,
            'status' => ServiceStatus::class,
            'starts_on' => 'date',
            'next_charge_date' => 'date',
        ];
    }

    /**
     * Un servicio con cobros pagados no se puede borrar: las llaves foráneas
     * están en cascada, así que borrarlo eliminaría también la constancia del
     * pago. Para esos se usa la cancelación.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->charges()->where('status', ChargeStatus::Pagado)->exists();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<AdCampaign, $this>
     */
    public function adCampaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * @return HasMany<ServiceInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(ServiceInstallment::class);
    }

    /**
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
