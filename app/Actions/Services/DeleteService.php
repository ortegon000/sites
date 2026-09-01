<?php

namespace App\Actions\Services;

use App\Models\Service;
use RuntimeException;

class DeleteService
{
    /**
     * Borrar un servicio se lleva por delante sus cobros y sus cuotas, porque
     * las llaves foráneas están en cascada. Eso está bien para un servicio
     * capturado por error, pero no para uno que ya se cobró: borraría la
     * constancia de que el cliente pagó. En ese caso se cancela, que detiene
     * los cobros futuros sin perder el historial.
     */
    public function handle(Service $service): void
    {
        if (! $service->canBeDeleted()) {
            throw new RuntimeException("El servicio [{$service->name}] tiene cobros pagados y no puede eliminarse.");
        }

        $service->delete();
    }
}
