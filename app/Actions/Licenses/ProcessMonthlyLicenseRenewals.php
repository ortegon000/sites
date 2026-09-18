<?php

namespace App\Actions\Licenses;

use App\Actions\Notifications\NotifyProjectTeam;
use App\Enums\LicenseBillingFrequency;
use App\Enums\LicenseStatus;
use App\Models\License;
use App\Notifications\LicenseRenewalDueNotification;

/**
 * Lo mensual no pasa por `Renewal` ni avisa al cliente —un correo cada mes
 * sería spam—, así que le toca su propio ciclo: recordatorio interno antes
 * del cobro, y la fecha avanza sola porque nadie la marca renovada a mano.
 */
class ProcessMonthlyLicenseRenewals
{
    private const REMINDER_DAYS = 3;

    public function __construct(private NotifyProjectTeam $notifyProjectTeam) {}

    public function handle(): void
    {
        $this->remindUpcoming();
        $this->advancePastDue();
    }

    private function remindUpcoming(): void
    {
        License::query()
            ->where('status', LicenseStatus::Activa)
            ->where('billing_frequency', LicenseBillingFrequency::Mensual)
            ->whereNotNull('renewal_date')
            ->whereNull('expiry_notified_at')
            ->whereBetween('renewal_date', [today(), today()->addDays(self::REMINDER_DAYS)])
            ->with('client')
            ->each(function (License $license): void {
                /** La licencia no cuelga de ningún proyecto, así que no hay equipo: avisa a los admins. */
                $this->notifyProjectTeam->handle(null, new LicenseRenewalDueNotification($license));

                $license->updateQuietly(['expiry_notified_at' => now()]);
            });
    }

    /**
     * El `booted()` de `License` limpia `expiry_notified_at` en cuanto
     * `renewal_date` cambia, así que el recordatorio del mes que sigue vuelve
     * a salir solo.
     */
    private function advancePastDue(): void
    {
        License::query()
            ->where('status', LicenseStatus::Activa)
            ->where('billing_frequency', LicenseBillingFrequency::Mensual)
            ->whereNotNull('renewal_date')
            ->where('renewal_date', '<', today())
            ->each(fn (License $license) => $license->update([
                'renewal_date' => $license->renewal_date->addMonthNoOverflow(),
            ]));
    }
}
