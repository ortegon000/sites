<?php

namespace App\Models;

use App\Enums\RenewalStatus;
use Carbon\CarbonImmutable;
use Database\Factories\RenewalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un ciclo de renovación de algo que caduca: un dominio, una licencia o un
 * servicio anual. Existe una fila por vencimiento, así que el historial queda:
 * qué se avisó, cuándo, y si el cliente renovó o se dio de baja.
 *
 * @property int $id
 * @property string $renewable_type
 * @property int $renewable_id
 * @property int $client_id
 * @property int|null $service_id
 * @property CarbonImmutable $due_date
 * @property RenewalStatus $status
 * @property string|null $amount
 * @property string $currency
 * @property CarbonImmutable|null $notified_at
 * @property CarbonImmutable|null $decided_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['renewable_type', 'renewable_id', 'client_id', 'service_id', 'due_date', 'status', 'amount', 'currency', 'notified_at', 'decided_at', 'notes'])]
class Renewal extends Model
{
    /** @use HasFactory<RenewalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RenewalStatus::class,
            'due_date' => 'date',
            'notified_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Cómo se llama lo que caduca, para el tablero y para el correo al cliente.
     */
    public function subject(): string
    {
        return match (true) {
            $this->renewable instanceof Domain => $this->renewable->name,
            $this->renewable instanceof License => $this->renewable->name,
            $this->renewable instanceof Service => $this->renewable->name,
            default => __('Renovación'),
        };
    }

    public function kindLabel(): string
    {
        return match (true) {
            $this->renewable instanceof Domain => __('Dominio'),
            $this->renewable instanceof License => __('Licencia'),
            $this->renewable instanceof Service => __('Servicio'),
            default => __('Otro'),
        };
    }

    public function isOpen(): bool
    {
        return in_array($this->status, RenewalStatus::open(), strict: true);
    }

    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->due_date, absolute: false);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function renewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
