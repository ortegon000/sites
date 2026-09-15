<?php

namespace App\Actions\EmailAccounts;

use App\Models\EmailAccount;

class ChangeEmailAccountPassword
{
    public function handle(EmailAccount $emailAccount, string $password): void
    {
        $provider = $emailAccount->provider;

        $provider->driver()->changePassword($provider, $emailAccount->email_address, $password);

        /**
         * A provider with a real API doesn't normally need a local copy —
         * but if this account already has one (someone recorded it by hand,
         * e.g. from an imported mailbox), keep it in sync rather than let it
         * go stale the moment the password actually rotates.
         */
        if ($provider->storesPasswordLocally() || $emailAccount->password !== null) {
            $emailAccount->update(['password' => $password]);
        }
    }
}
