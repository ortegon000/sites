<?php

namespace App\Actions\EmailAccounts;

use App\Models\EmailAccount;

class DeleteEmailAccount
{
    public function handle(EmailAccount $emailAccount): void
    {
        $provider = $emailAccount->provider;

        $provider->driver()->deleteMailbox($provider, $emailAccount->email_address);

        $emailAccount->delete();
    }
}
