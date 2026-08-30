<?php

namespace App\Models;

use App\Enums\ChargeStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_id
 * @property int|null $service_installment_id
 * @property string $amount
 * @property string $currency
 * @property ChargeStatus $status
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $due_soon_notified_at
 * @property CarbonImmutable|null $overdue_notified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['service_id', 'service_installment_id', 'amount', 'currency', 'status', 'due_date', 'paid_at', 'due_soon_notified_at', 'overdue_notified_at'])]
class Charge extends Model
{
    /** @use HasFactory<ChargeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ChargeStatus::class,
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'due_soon_notified_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<ServiceInstallment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(ServiceInstallment::class, 'service_installment_id');
    }
}
