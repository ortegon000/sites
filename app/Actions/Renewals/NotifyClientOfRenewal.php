<?php

namespace App\Actions\Renewals;

use App\Enums\RenewalStatus;
use App\Models\Renewal;
use App\Notifications\RenewalNoticeNotification;
use Illuminate\Support\Facades\Notification;

class NotifyClientOfRenewal
{
    /**
     * Avisa al cliente y deja constancia de que ya se avisó, que es el estado
     * que antes solo vivía en la cabeza de quien mandó el correo.
     *
     * Va a los contactos de la empresa que tengan correo. Si no hay ninguno no
     * se marca como avisado: fingir que se avisó es peor que no avisar.
     */
    public function handle(Renewal $renewal): bool
    {
        $emails = $renewal->client->contacts()
            ->whereNotNull('email')
            ->pluck('email')
            ->all();

        if ($emails === []) {
            return false;
        }

        Notification::route('mail', $emails)->notify(new RenewalNoticeNotification($renewal));

        $renewal->update([
            'status' => RenewalStatus::Avisado,
            'notified_at' => now(),
        ]);

        return true;
    }
}
