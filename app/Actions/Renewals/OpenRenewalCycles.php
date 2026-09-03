<?php

namespace App\Actions\Renewals;

use App\Enums\DomainManagement;
use App\Enums\DomainStatus;
use App\Enums\LicenseStatus;
use App\Enums\RenewalStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceStatus;
use App\Models\Domain;
use App\Models\License;
use App\Models\Renewal;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;

class OpenRenewalCycles
{
    /**
     * Cuántos días antes aparece algo en el tablero. Dos meses da margen para
     * avisar, esperar la respuesta del cliente y cobrar antes de la fecha.
     */
    public const HORIZON_DAYS = 60;

    /**
     * Abre el ciclo de todo lo que caduca dentro del horizonte y todavía no lo
     * tiene abierto: dominios que renovamos nosotros, licencias vigentes y
     * servicios anuales. Es idempotente —la llave única por vencimiento evita
     * que la corrida diaria duplique ciclos— y devuelve cuántos abrió.
     */
    public function handle(): int
    {
        $opened = 0;

        Domain::query()
            ->where('management', DomainManagement::Managed)
            ->where('status', DomainStatus::Activo)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [today(), today()->addDays(self::HORIZON_DAYS)])
            ->each(function (Domain $domain) use (&$opened): void {
                $opened += $this->open($domain, $domain->client_id, $domain->expires_at, null, $domain->client->currency) ? 1 : 0;
            });

        License::query()
            ->where('status', LicenseStatus::Activa)
            ->whereNotNull('renewal_date')
            ->whereBetween('renewal_date', [today(), today()->addDays(self::HORIZON_DAYS)])
            ->each(function (License $license) use (&$opened): void {
                $opened += $this->open($license, $license->client_id, $license->renewal_date, $license->cost, $license->currency) ? 1 : 0;
            });

        Service::query()
            ->where('status', ServiceStatus::Activo)
            ->where('billing_frequency', ServiceBillingFrequency::Annual)
            ->whereNotNull('next_charge_date')
            ->whereBetween('next_charge_date', [today(), today()->addDays(self::HORIZON_DAYS)])
            ->each(function (Service $service) use (&$opened): void {
                $opened += $this->open($service, $service->client_id, $service->next_charge_date, $service->amount, $service->currency) ? 1 : 0;
            });

        return $opened;
    }

    private function open(Model $renewable, int $clientId, mixed $dueDate, ?string $amount, string $currency): bool
    {
        $exists = Renewal::query()
            ->where('renewable_type', $renewable->getMorphClass())
            ->where('renewable_id', $renewable->getKey())
            ->whereDate('due_date', $dueDate)
            ->exists();

        if ($exists) {
            return false;
        }

        Renewal::create([
            'renewable_type' => $renewable->getMorphClass(),
            'renewable_id' => $renewable->getKey(),
            'client_id' => $clientId,
            'due_date' => $dueDate,
            'status' => RenewalStatus::PorAvisar,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return true;
    }
}
