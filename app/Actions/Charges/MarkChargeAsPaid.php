<?php

namespace App\Actions\Charges;

use App\Models\Charge;

class MarkChargeAsPaid
{
    public function __construct(private RecordChargePayment $recordChargePayment) {}

    /**
     * Atajo de "marcar pagado": registra el restante como un abono de hoy en vez
     * de tocar el estatus a mano, para que un cobro pagado siempre tenga con qué
     * respaldarse y el restante nunca contradiga a la insignia.
     */
    public function handle(Charge $charge): Charge
    {
        $remaining = $charge->remainingAmount();

        if ($remaining > 0) {
            $this->recordChargePayment->handle($charge, [
                'amount' => $remaining,
                'paid_on' => today()->toDateString(),
            ]);
        }

        return $charge->refresh();
    }
}
