<?php

namespace App\Models;

use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use Carbon\CarbonImmutable;
use Database\Factories\QuoteLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un concepto dentro de una cotización: "Diseño y desarrollo" a $38,000 único
 * pago, "Hosting anual" a $1,800 cada año, en la misma propuesta. Al aceptarse
 * la cotización, cada renglón nace como su propia línea cobrable.
 *
 * @property int $id
 * @property int $quote_id
 * @property int|null $service_id
 * @property string $name
 * @property string|null $description
 * @property ServiceCategory $category
 * @property ServiceBillingFrequency $billing_frequency
 * @property string $amount
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['quote_id', 'service_id', 'name', 'description', 'category', 'billing_frequency', 'amount'])]
class QuoteLineItem extends Model
{
    /** @use HasFactory<QuoteLineItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'billing_frequency' => ServiceBillingFrequency::class,
        ];
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
