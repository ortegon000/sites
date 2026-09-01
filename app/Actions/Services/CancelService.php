<?php

namespace App\Actions\Services;

use App\Enums\ServiceStatus;
use App\Models\Service;

class CancelService
{
    /**
     * Detiene un servicio conservando su historial de cobros. Se limpia
     * `next_charge_date` además de cambiar el estatus: es el campo que empuja
     * la generación de cobros recurrentes, y dejarlo puesto haría que un
     * servicio cancelado siguiera acumulando cobros pendientes.
     */
    public function handle(Service $service): void
    {
        $service->update([
            'status' => ServiceStatus::Cancelado,
            'next_charge_date' => null,
        ]);
    }
}
