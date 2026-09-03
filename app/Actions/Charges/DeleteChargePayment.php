<?php

namespace App\Actions\Charges;

use App\Models\ChargePayment;

class DeleteChargePayment
{
    public function handle(ChargePayment $payment): void
    {
        $charge = $payment->charge;

        $payment->delete();

        $charge->syncStatusFromPayments();
    }
}
