<?php

namespace App\Actions\Notifications;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotifyProjectTeam
{
    /**
     * Send an internal notification to everyone who should act on it: every
     * admin, plus the staff assigned to the project it belongs to. Some records
     * (a domain with no project yet) have no team of their own, in which case
     * only the admins hear about it.
     */
    public function handle(?Project $project, Notification $notification): void
    {
        $recipients = User::query()
            ->where('role', UserRole::Admin)
            ->when($project, fn ($query) => $query->orWhere(function ($subQuery) use ($project): void {
                $subQuery->where('role', UserRole::Staff)
                    ->whereHas('projects', fn ($q) => $q->whereKey($project->id));
            }))
            ->get();

        NotificationFacade::send($recipients, $notification);
    }
}
