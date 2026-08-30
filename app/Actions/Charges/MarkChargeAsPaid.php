<?php

namespace App\Actions\Charges;

use App\Enums\ChargeStatus;
use App\Models\Charge;

class MarkChargeAsPaid
{
    public function handle(Charge $charge): Charge
    {
        $charge->update([
            'status' => ChargeStatus::Pagado,
            'paid_at' => now(),
        ]);

        return $charge;
    }
}
