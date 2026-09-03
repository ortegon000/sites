<?php

namespace App\Models;

use App\Enums\ChargeStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $service_id
 * @property int|null $service_installment_id
 * @property string|null $concept
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
#[Fillable(['service_id', 'service_installment_id', 'concept', 'amount', 'currency', 'status', 'due_date', 'paid_at', 'due_soon_notified_at', 'overdue_notified_at'])]
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
     * El concepto se puede editar por cobro, porque los montos y las glosas
     * cambian entre periodos. Sin capturarlo, el nombre del servicio sirve.
     */
    public function conceptLabel(): string
    {
        return $this->concept ?: $this->service->name;
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function remainingAmount(): float
    {
        return round(max(0, (float) $this->amount - $this->paidAmount()), 2);
    }

    /**
     * Deriva el estatus de los abonos: pagado si ya se cubrió, vencido si pasó
     * la fecha con saldo (aunque tenga abonos, porque lo que falta sigue
     * debiéndose), parcial si hay algo abonado, y pendiente si no.
     */
    public function syncStatusFromPayments(): void
    {
        $paid = $this->paidAmount();
        $isSettled = $paid + 0.001 >= (float) $this->amount;

        $this->status = match (true) {
            $isSettled => ChargeStatus::Pagado,
            $this->due_date !== null && $this->due_date->isBefore(today()) => ChargeStatus::Vencido,
            $paid > 0 => ChargeStatus::Parcial,
            default => ChargeStatus::Pendiente,
        };

        $lastPaidOn = $this->payments()->max('paid_on');

        $this->paid_at = $isSettled && $lastPaidOn !== null
            ? CarbonImmutable::parse($lastPaidOn)
            : null;

        $this->save();
    }

    /**
     * Cuántos cobros hay y cuánto falta por cobrar de ellos: el monto menos sus
     * abonos. Sumar el monto a secas contaría entero un cobro ya abonado a la
     * mitad, que es justo lo que el dueño revisa en la columna "Restante".
     *
     * @param  Builder<Charge>  $query
     */
    #[Scope]
    protected function selectRemainingTotals(Builder $query): void
    {
        $query->selectRaw('count(*) as count, coalesce(sum(charges.amount - coalesce((select sum(charge_payments.amount) from charge_payments where charge_payments.charge_id = charges.id), 0)), 0) as total');
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

    /**
     * @return HasMany<ChargePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ChargePayment::class);
    }
}
