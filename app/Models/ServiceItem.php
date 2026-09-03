<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ServiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una subtarea de un servicio: las tres visitas que cubre un mantenimiento
 * cuatrimestral de $1,000 al año —no $1,000 por visita—, o la lista numerada
 * de cambios que hoy se escribe dentro del concepto de "Mejora continua".
 *
 * @property int $id
 * @property int $service_id
 * @property string $description
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['service_id', 'description', 'due_date', 'completed_at'])]
class ServiceItem extends Model
{
    /** @use HasFactory<ServiceItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
