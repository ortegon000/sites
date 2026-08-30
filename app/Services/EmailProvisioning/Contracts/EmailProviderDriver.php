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
     * List the mailboxes that currently exist on the remote provider.
     *
     * @return array<int, string>
     */
    public function listMailboxes(EmailProvider $provider): array;

    /**
     * Get the IMAP/SMTP connection settings a mailbox owner would need.
     *
     * @return array<string, string>
     */
    public function getConnectionSettings(EmailProvider $provider): array;
}
