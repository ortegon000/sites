<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * El contrato de lo que se presta a un cliente.
 *
 * Se genera con lo que ya vive en el sistema —servicios, montos, vigencias— y
 * guarda su texto completo, no solo sus datos: si el servicio sube de precio
 * el mes que entra, el contrato firmado tiene que seguir diciendo lo que se
 * firmó.
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $project_id
 * @property int|null $quote_id
 * @property string $number
 * @property string $title
 * @property ContractStatus $status
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property string $currency
 * @property string $body
 * @property string|null $signed_by
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $signed_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['client_id', 'project_id', 'quote_id', 'number', 'title', 'status', 'starts_on', 'ends_on', 'currency', 'body', 'signed_by', 'sent_at', 'signed_at', 'notes'])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sent_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    /**
     * El folio del siguiente contrato del año. Consecutivo por año porque es
     * como se archivan: "el CT-2026-0007" se busca en la carpeta de 2026.
     */
    public static function nextNumber(): string
    {
        $year = today()->year;
        $count = self::query()->where('number', 'like', "CT-{$year}-%")->count();

        return sprintf('CT-%d-%04d', $year, $count + 1);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ContractStatus::editable(), strict: true);
    }

    public function isExpired(): bool
    {
        return $this->ends_on !== null && $this->ends_on->isBefore(today());
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
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Las líneas cobrables que ampara. El texto guarda los montos del día en
     * que se emitió; esta relación es para saber, desde el servicio, con qué
     * contrato está respaldado.
     *
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }
}
