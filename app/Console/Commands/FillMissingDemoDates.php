<?php

namespace App\Console\Commands;

use App\Actions\Renewals\OpenRenewalCycles;
use App\Models\Domain;
use App\Models\License;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Rellena con fechas de prueba lo que el libro de hosting no traía.
 *
 * El archivo real del dueño no tenía ni una sola fecha de renovación —por eso
 * se revisaba a mano—, así que después de importarlo el tablero de
 * renovaciones queda vacío y no hay nada que probar. Este comando inventa
 * fechas plausibles para ese hueco: es una herramienta de demo, no un
 * importador. Las fechas reales las captura el dueño y estas hay que
 * sustituirlas en cuanto las tenga.
 *
 * Solo toca registros con el dato en nulo, así que correrlo dos veces no
 * cambia nada de lo ya capturado.
 */
class FillMissingDemoDates extends Command
{
    protected $signature = 'demo:fill-dates';

    protected $description = 'Rellena con fechas de prueba las renovaciones que el libro de hosting no traía.';

    public function handle(OpenRenewalCycles $openRenewalCycles): void
    {
        $domains = $this->fillDomains();
        $licenses = $this->fillLicenses();
        $cycles = $openRenewalCycles->handle();

        $this->info("Dominios con fecha de renovación nueva: {$domains}.");
        $this->info("Licencias con fecha de renovación nueva: {$licenses}.");
        $this->info("Ciclos de renovación abiertos: {$cycles}.");
        $this->comment('Son fechas de prueba: sustitúyelas por las reales en cuanto las tengas.');
    }

    /**
     * Un dominio se renueva en el aniversario de su alta, así que esa es la
     * fecha que se inventa cuando el libro traía cuándo entró al VPS. Los que
     * no la traían se reparten a lo largo del año, para que el tablero tenga
     * casos cerca y lejos en vez de veinte vencimientos el mismo día.
     */
    private function fillDomains(): int
    {
        $filled = 0;

        Domain::query()
            ->whereNull('expires_at')
            ->orderBy('id')
            ->each(function (Domain $domain) use (&$filled): void {
                $anchor = $domain->hosted_since ?? $domain->registered_at;

                $expiresAt = $anchor !== null
                    ? $this->nextAnniversary($anchor)
                    : today()->toImmutable()->addDays(15 + ($domain->id * 17) % 330);

                $domain->forceFill([
                    'expires_at' => $expiresAt,
                    'registered_at' => $domain->registered_at ?? $expiresAt->subYear(),
                ])->save();

                $filled++;
            });

        return $filled;
    }

    private function fillLicenses(): int
    {
        $filled = 0;

        License::query()
            ->whereNull('renewal_date')
            ->orderBy('id')
            ->each(function (License $license) use (&$filled): void {
                $license->forceFill([
                    'renewal_date' => today()->toImmutable()->addDays(20 + ($license->id * 23) % 300),
                ])->save();

                $filled++;
            });

        return $filled;
    }

    private function nextAnniversary(CarbonImmutable $date): CarbonImmutable
    {
        $anniversary = $date->setYear(today()->year);

        return $anniversary->lt(today()) ? $anniversary->addYear() : $anniversary;
    }
}
