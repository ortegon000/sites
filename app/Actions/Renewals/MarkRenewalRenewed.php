<?php

namespace App\Actions\Renewals;

use App\Actions\Services\CreateServiceWithSchedule;
use App\Enums\RenewalStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Models\Domain;
use App\Models\License;
use App\Models\Renewal;
use App\Models\Service;

class MarkRenewalRenewed
{
    public function __construct(private CreateServiceWithSchedule $createServiceWithSchedule) {}

    /**
     * El cliente renovó: se empuja la fecha de caducidad un año y se genera la
     * línea cobrable de la renovación.
     *
     * Un servicio anual no genera línea: ya se cobra solo por su calendario, y
     * duplicarla cobraría dos veces lo mismo. Para dominios y licencias, en
     * cambio, la renovación es justo la línea que antes se apuntaba a mano.
     */
    public function handle(Renewal $renewal): Renewal
    {
        $renewable = $renewal->renewable;

        $service = $this->billableLineFor($renewal);

        match (true) {
            $renewable instanceof Domain => $renewable->update([
                'expires_at' => $renewal->due_date->addYear(),
            ]),
            $renewable instanceof License => $renewable->update([
                'renewal_date' => $renewal->due_date->addYear(),
            ]),
            default => null,
        };

        $renewal->update([
            'status' => RenewalStatus::Renovado,
            'decided_at' => now(),
            'service_id' => $service?->id,
        ]);

        return $renewal;
    }

    private function billableLineFor(Renewal $renewal): ?Service
    {
        $renewable = $renewal->renewable;

        if ($renewable instanceof Service || $renewal->amount === null || (float) $renewal->amount <= 0) {
            return null;
        }

        return $this->createServiceWithSchedule->handle($renewal->client, [
            'name' => __('Renovación').' · '.$renewal->subject(),
            'description' => null,
            'category' => $renewable instanceof Domain ? ServiceCategory::Domain : ServiceCategory::Other,
            'domain_id' => $renewable instanceof Domain ? $renewable->id : null,
            'billing_frequency' => ServiceBillingFrequency::OneTime,
            'amount' => $renewal->amount,
            'currency' => $renewal->currency,
            'status' => ServiceStatus::Activo,
            'starts_on' => $renewal->due_date->toDateString(),
            'installments_count' => null,
        ]);
    }
}
