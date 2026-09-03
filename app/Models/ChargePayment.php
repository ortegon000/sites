<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ChargePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un abono a un cobro. El cobro no es binario: los pagos parciales están por
 * todas partes en los datos reales —dos transferencias en fechas distintas
 * para una misma línea— y el estatus del cobro se deriva de estos renglones.
 *
 * @property int $id
 * @property int $charge_id
 * @property string $amount
 * @property CarbonImmutable $paid_on
 * @property string|null $method
 * @property string|null $account
 * @property string|null $reference
 * @property string|null $invoice_reference
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['charge_id', 'amount', 'paid_on', 'method', 'account', 'reference', 'invoice_reference'])]
class ChargePayment extends Model
{
    /** @use HasFactory<ChargePaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Charge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
