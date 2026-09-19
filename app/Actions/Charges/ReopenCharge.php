<?php

namespace App\Actions\Charges;

use App\Models\Charge;
use Illuminate\Support\Facades\DB;

class ReopenCharge
{
    /**
     * Regresa un cobro pagado a pendiente. El estatus se deriva de los abonos,
     * así que reabrirlo es quitarle los que lo cubrían: sin ellos el cobro
     * queda pendiente, o vencido si su fecha ya pasó.
     */
    public function handle(Charge $charge): Charge
    {
        DB::transaction(function () use ($charge): void {
            $charge->payments()->delete();
            $charge->unsetRelation('payments');
            $charge->syncStatusFromPayments();
        });

        return $charge;
    }
}
