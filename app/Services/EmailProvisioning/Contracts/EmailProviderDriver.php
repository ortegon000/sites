<?php

namespace App\Services\EmailProvisioning\Contracts;

use App\Models\EmailProvider;

interface EmailProviderDriver
{
    /**
     * Create a mailbox on the remote provider for the given address.
     */
    public function createMailbox(EmailProvider $provider, string $emailAddress, string $password): void;

    /**
     * Permanently remove a mailbox from the remote provider.
     */
    public function deleteMailbox(EmailProvider $provider, string $emailAddress): void;

    /**
     * Change the password of an existing mailbox on the remote provider.
     */
    public function changePassword(EmailProvider $provider, string $emailAddress, string $password): void;

    /**
     * List the mailboxes that currently exist on the remote provider for a
     * domain. Real providers list per domain (a cPanel account or an MXroute
     * domain holds its own mailboxes), and the import screen always asks about
     * one domain at a time.
     *
     * @return array<int, string>
     */
    public function listMailboxes(EmailProvider $provider, string $domain): array;

    /**
     * Get the IMAP/SMTP connection settings a mailbox owner would need.
     *
     * @return array<string, string>
     */
    public function getConnectionSettings(EmailProvider $provider): array;
}
