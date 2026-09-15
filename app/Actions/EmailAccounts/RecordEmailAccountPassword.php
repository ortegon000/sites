<?php

namespace App\Actions\EmailAccounts;

use App\Models\EmailAccount;

/**
 * Documents the password of a mailbox that already exists on the provider —
 * an imported account whose password was never captured. Unlike
 * ChangeEmailAccountPassword, this never calls the provider: the mailbox
 * isn't changing, only our record of it.
 */
class RecordEmailAccountPassword
{
    public function handle(EmailAccount $emailAccount, string $password): void
    {
        $emailAccount->update(['password' => $password]);
    }
}
