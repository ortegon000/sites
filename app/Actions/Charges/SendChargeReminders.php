<?php

namespace App\Actions\Charges;

use App\Enums\ChargeStatus;
use App\Enums\UserRole;
use App\Models\Charge;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ChargeDueSoonNotification;
use App\Notifications\ChargeOverdueNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class SendChargeReminders
{
    private const DUE_SOON_DAYS = 3;

    public function handle(): void
    {
        $this->sendDueSoon();
        $this->sendOverdue();
    }

    private function sendDueSoon(): void
    {
        Charge::query()
            ->where('status', ChargeStatus::Pendiente)
            ->whereNull('due_soon_notified_at')
            ->whereBetween('due_date', [today(), today()->addDays(self::DUE_SOON_DAYS)])
            ->with('service.project')
            ->each(function (Charge $charge): void {
                $this->notifyProject($charge->service->project, new ChargeDueSoonNotification($charge));

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
                $this->notifyProject($charge->service->project, new ChargeOverdueNotification($charge));

                $charge->update(['overdue_notified_at' => now()]);
            });
    }

    private function notifyProject(Project $project, Notification $notification): void
    {
        $recipients = User::query()
            ->where('role', UserRole::Admin)
            ->orWhere(function ($query) use ($project): void {
                $query->where('role', UserRole::Staff)
                    ->whereHas('projects', fn ($q) => $q->whereKey($project->id));
            })
            ->get();

        NotificationFacade::send($recipients, $notification);
    }
}
