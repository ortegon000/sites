<?php

namespace App\Models;

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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $project_id
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
#[Fillable(['client_id', 'project_id', 'domain_id', 'ad_campaign_id', 'name', 'description', 'category', 'billing_frequency', 'amount', 'currency', 'status', 'starts_on', 'next_charge_date', 'installments_count'])]
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
     * Un servicio con cobros abonados no se puede borrar: las llaves foráneas
     * están en cascada, así que borrarlo eliminaría también la constancia del
     * pago. Basta un abono parcial para que exista esa constancia. Para esos
     * servicios se usa la cancelación.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->charges()->whereHas('payments')->exists();
    }

    /**
     * @return MorphMany<Renewal, $this>
     */
    public function renewals(): MorphMany
    {
        return $this->morphMany(Renewal::class, 'renewable');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * El proyecto es opcional: agrupa los servicios de un trabajo grande, pero
     * una línea suelta cuelga del cliente sin pasar por él.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<ServiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ServiceItem::class);
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

    /**
     * Los abonos de todos sus cobros, para poder sumar lo cobrado de una línea
     * sin recorrer cobro por cobro.
     *
     * @return HasManyThrough<ChargePayment, Charge, $this>
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(ChargePayment::class, Charge::class);
    }
}
