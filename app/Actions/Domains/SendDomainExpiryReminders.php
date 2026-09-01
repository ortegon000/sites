<?php

namespace App\Actions\Domains;

use App\Actions\Notifications\NotifyProjectTeam;
use App\Enums\DomainManagement;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Notifications\DomainExpiringNotification;

class SendDomainExpiryReminders
{
    private const EXPIRING_SOON_DAYS = 30;

    public function __construct(private NotifyProjectTeam $notifyProjectTeam) {}

    /**
     * Warn about domains we renew and bill for that are about to expire. A
     * month of notice is the useful window: enough to chase the client for the
     * renewal, or to confirm the registrar's auto-renew will go through.
     *
     * Domains we only track are left out — those are renewed by whoever owns
     * them, not by us.
     */
    public function handle(): void
    {
        Domain::query()
            ->where('management', DomainManagement::Managed)
            ->where('status', DomainStatus::Activo)
            ->whereNotNull('expires_at')
            ->whereNull('expiry_notified_at')
            ->whereBetween('expires_at', [today(), today()->addDays(self::EXPIRING_SOON_DAYS)])
            ->with(['client', 'project'])
            ->each(function (Domain $domain): void {
                $this->notifyProjectTeam->handle($domain->project, new DomainExpiringNotification($domain));

                $domain->updateQuietly(['expiry_notified_at' => now()]);
            });
    }
}
