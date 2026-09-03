<?php

namespace App\Actions\Charges;

use App\Actions\Notifications\NotifyProjectTeam;
use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Notifications\ChargeDueSoonNotification;
use App\Notifications\ChargeOverdueNotification;

class SendChargeReminders
{
    private const DUE_SOON_DAYS = 3;

    public function __construct(private NotifyProjectTeam $notifyProjectTeam) {}

    public function handle(): void
    {
        $this->sendDueSoon();
        $this->sendOverdue();
    }

    private function sendDueSoon(): void
    {
        Charge::query()
            ->whereIn('status', [ChargeStatus::Pendiente, ChargeStatus::Parcial])
            ->whereNull('due_soon_notified_at')
            ->whereBetween('due_date', [today(), today()->addDays(self::DUE_SOON_DAYS)])
            ->with('service.project')
            ->each(function (Charge $charge): void {
                $this->notifyProjectTeam->handle($charge->service->project, new ChargeDueSoonNotification($charge));

                $charge->update(['due_soon_notified_at' => now()]);
            });
    }

    private function sendOverdue(): void
    {
        Charge::query()
            ->where('status', ChargeStatus::Vencido)
            ->whereNull('overdue_notified_at')
            ->with('service.project')
            ->each(function (Charge $charge): void {
                $this->notifyProjectTeam->handle($charge->service->project, new ChargeOverdueNotification($charge));

                $charge->update(['overdue_notified_at' => now()]);
            });
    }
}
