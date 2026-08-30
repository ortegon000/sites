<?php

namespace App\Actions\EmailAccounts;

use App\Models\EmailAccount;

class ChangeEmailAccountPassword
{
    public function handle(EmailAccount $emailAccount, string $password): void
    {
        $provider = $emailAccount->provider;

        $provider->driver()->changePassword($provider, $emailAccount->email_address, $password);
    }
}
