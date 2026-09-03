<?php

namespace App\Actions\Renewals;

use App\Enums\RenewalStatus;
use App\Models\Renewal;

class SendRenewalNotices
{
    /**
     * Cuántos días antes se le avisa al cliente. Un mes es la ventana útil:
     * alcanza para que decida y para cobrarle antes de la fecha.
     */
    public const NOTICE_DAYS = 30;

    public function __construct(private NotifyClientOfRenewal $notifyClientOfRenewal) {}

    /**
     * Manda el aviso de los ciclos que entran en la ventana y todavía no se
     * avisan. Los que no tienen contacto con correo se quedan por avisar, a la
     * vista en el tablero, en vez de darse por avisados en falso.
     */
    public function handle(): int
    {
        $sent = 0;

        Renewal::query()
            ->where('status', RenewalStatus::PorAvisar)
            ->whereBetween('due_date', [today(), today()->addDays(self::NOTICE_DAYS)])
            ->with(['client', 'renewable'])
            ->each(function (Renewal $renewal) use (&$sent): void {
                $sent += $this->notifyClientOfRenewal->handle($renewal) ? 1 : 0;
            });

        return $sent;
    }
}
