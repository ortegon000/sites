<?php

namespace App\Actions\Charges;

use App\Enums\ChargeStatus;
use App\Models\Charge;

class MarkOverdueCharges
{
    public function handle(): int
    {
        return Charge::query()
            ->whereIn('status', [ChargeStatus::Pendiente, ChargeStatus::Parcial])
            ->where('due_date', '<', today())
            ->update(['status' => ChargeStatus::Vencido]);
    }
}
