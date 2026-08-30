<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ServiceInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $service_id
 * @property int $installment_number
 * @property string $amount
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['service_id', 'installment_number', 'amount', 'due_date'])]
class ServiceInstallment extends Model
{
    /** @use HasFactory<ServiceInstallmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
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
     * @return HasOne<Charge, $this>
     */
    public function charge(): HasOne
    {
        return $this->hasOne(Charge::class);
    }
}
