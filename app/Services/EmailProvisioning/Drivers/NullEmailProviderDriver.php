<?php

namespace App\Services\EmailProvisioning\Drivers;

use App\Models\EmailProvider;
use App\Services\EmailProvisioning\Contracts\EmailProviderDriver;

/**
 * A simulated driver with no real provider behind it yet. It doesn't call
 * any remote API — it just satisfies the contract so the rest of the
 * application (actions, policies, UI) can be built and tested before a real
 * MXroute/cPanel/Hostinger integration exists. Swap the resolved class in
 * EmailProvider::driver() once a real driver is ready.
 */
class NullEmailProviderDriver implements EmailProviderDriver
{
    public function createMailbox(EmailProvider $provider, string $emailAddress, string $password): void
    {
        // No-op: nothing to provision remotely yet.
    }

    public function deleteMailbox(EmailProvider $provider, string $emailAddress): void
    {
        // No-op: nothing to remove remotely yet.
    }

    public function changePassword(EmailProvider $provider, string $emailAddress, string $password): void
    {
        // No-op: nothing to update remotely yet.
    }

    public function listMailboxes(EmailProvider $provider): array
    {
        return $provider->emailAccounts()->pluck('email_address')->all();
    }

    public function getConnectionSettings(EmailProvider $provider): array
    {
        return [
            'imap_host' => 'imap.simulado.test',
            'imap_port' => '993',
            'smtp_host' => 'smtp.simulado.test',
            'smtp_port' => '587',
        ];
    }
}
