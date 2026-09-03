<?php

namespace App\Services\EmailProvisioning\Drivers;

use App\Models\EmailProvider;
use App\Services\EmailProvisioning\Contracts\EmailProviderDriver;

/**
 * A provider the agency administers by hand, with no API to call: the mailbox
 * is created, changed or removed by a person in the provider's own control
 * panel, and this driver only records that it happened. Because nothing can
 * reset a password later, mailboxes on this driver keep their password in our
 * own database (see EmailProviderDriverType::storesPasswordLocally), and the
 * connection settings the client sees come from the provider record itself.
 */
class ManualEmailProviderDriver implements EmailProviderDriver
{
    public function createMailbox(EmailProvider $provider, string $emailAddress, string $password): void
    {
        // No-op: a person creates the mailbox in the provider's panel.
    }

    public function deleteMailbox(EmailProvider $provider, string $emailAddress): void
    {
        // No-op: a person removes the mailbox in the provider's panel.
    }

    public function changePassword(EmailProvider $provider, string $emailAddress, string $password): void
    {
        // No-op: a person changes it in the panel; we only store the new value.
    }

    public function listMailboxes(EmailProvider $provider, string $domain): array
    {
        return $provider->emailAccounts()
            ->whereHas('domain', fn ($query) => $query->where('name', $domain))
            ->pluck('email_address')
            ->all();
    }

    public function getConnectionSettings(EmailProvider $provider, ?string $domain = null): array
    {
        $settings = $provider->connection_settings ?? [];

        return [
            'imap_host' => $this->resolve($settings['imap_host'] ?? null, $domain),
            'imap_port' => (string) ($settings['imap_port'] ?? ''),
            'smtp_host' => $this->resolve($settings['smtp_host'] ?? null, $domain),
            'smtp_port' => (string) ($settings['smtp_port'] ?? ''),
            'webmail_url' => $this->resolve($settings['webmail_url'] ?? null, $domain),
        ];
    }

    /**
     * Sustituye `{dominio}` por el dominio del buzón, para que un proveedor
     * cuya configuración es `mail.{dominio}` se capture una sola vez y sirva
     * para todos sus dominios.
     */
    private function resolve(?string $template, ?string $domain): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        if ($domain === null) {
            return $template;
        }

        return str_replace(['{dominio}', '{domain}'], $domain, $template);
    }
}
