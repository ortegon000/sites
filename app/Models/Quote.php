<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Carbon\CarbonImmutable;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Trabajo cotizado y todavía no aceptado.
 *
 * No es un servicio con otro estatus: un servicio genera cobros, y una
 * cotización no debe generar ninguno hasta que el cliente diga que sí. Al
 * aceptarse, cada renglón nace como su propia línea cobrable y la cotización
 * se queda como el registro de qué se ofreció, por cuánto y cuándo se decidió.
 *
 * El monto no vive aquí: una cotización de agencia rara vez es un solo
 * concepto, así que vive repartido en sus renglones (`lineItems`).
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $project_id
 * @property bool $is_project
 * @property string|null $public_token El constructor lo genera si no viene dado; por eso se comprueba contra nulo.
 * @property string $name
 * @property string|null $description
 * @property string $currency
 * @property QuoteStatus $status
 * @property CarbonImmutable|null $valid_until
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $decided_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['client_id', 'project_id', 'is_project', 'name', 'description', 'currency', 'status', 'valid_until', 'sent_at', 'decided_at', 'notes'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_project' => 'boolean',
            'status' => QuoteStatus::class,
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * El token del enlace público nace con la cotización, no se captura: por
     * eso no está en `#[Fillable]` y se genera aquí, en el constructor y no
     * en un evento `creating` -los seeders corren con `WithoutModelEvents`, y
     * uno de esos apagaría esta generación silenciosamente.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (! $this->exists && $this->public_token === null) {
            $this->public_token = Str::random(40);
        }
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
     * @return HasMany<QuoteLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class);
    }
}
