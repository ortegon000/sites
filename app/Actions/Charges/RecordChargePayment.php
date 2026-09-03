<?php

namespace App\Actions\Charges;

use App\Models\Charge;
use App\Models\ChargePayment;

class RecordChargePayment
{
    /**
     * @param  array{amount: string|float, paid_on: string, method?: string|null, account?: string|null, reference?: string|null, invoice_reference?: string|null}  $attributes
     */
    public function handle(Charge $charge, array $attributes): ChargePayment
    {
        $payment = $charge->payments()->create($attributes);

        $charge->syncStatusFromPayments();

        return $payment;
    }
}
