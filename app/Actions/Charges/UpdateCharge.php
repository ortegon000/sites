<?php

namespace App\Actions\Charges;

use App\Models\Charge;

class UpdateCharge
{
    /**
     * @param  array{concept: string|null, amount: string|float, due_date: string}  $attributes
     */
    public function handle(Charge $charge, array $attributes): Charge
    {
        $charge->update($attributes);

        $charge->syncStatusFromPayments();

        return $charge;
    }
}
