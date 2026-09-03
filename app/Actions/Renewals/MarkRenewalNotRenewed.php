<?php

namespace App\Actions\Renewals;

use App\Actions\Services\CancelService;
use App\Enums\DomainStatus;
use App\Enums\LicenseStatus;
use App\Enums\RenewalStatus;
use App\Models\Domain;
use App\Models\License;
use App\Models\Renewal;
use App\Models\Service;

class MarkRenewalNotRenewed
{
    public function __construct(private CancelService $cancelService) {}

    /**
     * El cliente no renovó: se da de baja lo que caducaba, cada cosa a su modo.
     * El dominio queda expirado, la licencia cancelada y el servicio anual se
     * cancela, que además le quita su próximo cobro.
     */
    public function handle(Renewal $renewal): Renewal
    {
        $renewable = $renewal->renewable;

        match (true) {
            $renewable instanceof Domain => $renewable->update([
                'status' => DomainStatus::Expirado,
                'auto_renew' => false,
            ]),
            $renewable instanceof License => $renewable->update([
                'status' => LicenseStatus::Cancelada,
                'auto_renew' => false,
            ]),
            $renewable instanceof Service => $this->cancelService->handle($renewable),
            default => null,
        };

        $renewal->update([
            'status' => RenewalStatus::NoRenovado,
            'decided_at' => now(),
        ]);

        return $renewal;
    }
}
