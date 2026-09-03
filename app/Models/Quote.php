<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use Carbon\CarbonImmutable;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trabajo cotizado y todavía no aceptado.
 *
 * No es un servicio con otro estatus: un servicio genera cobros, y una
 * cotización no debe generar ninguno hasta que el cliente diga que sí. Al
 * aceptarse nace la línea cobrable y la cotización se queda como el registro
 * de qué se ofreció, por cuánto y cuándo se decidió.
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $project_id
 * @property int|null $service_id
 * @property string $name
 * @property string|null $description
 * @property ServiceCategory $category
 * @property ServiceBillingFrequency $billing_frequency
 * @property string $amount
 * @property string $currency
 * @property QuoteStatus $status
 * @property CarbonImmutable|null $valid_until
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $decided_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['client_id', 'project_id', 'service_id', 'name', 'description', 'category', 'billing_frequency', 'amount', 'currency', 'status', 'valid_until', 'sent_at', 'decided_at', 'notes'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'billing_frequency' => ServiceBillingFrequency::class,
            'status' => QuoteStatus::class,
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, QuoteStatus::open(), strict: true);
    }

    public function hasExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isBefore(today());
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
