<?php

namespace App\Actions\EmailAccounts;

use App\Enums\EmailAccountStatus;
use App\Models\Client;
use App\Models\EmailAccount;
use App\Models\EmailProvider;

class ProvisionEmailAccount
{
    public function handle(Client $client, EmailProvider $provider, string $emailAddress, string $password): EmailAccount
    {
        $provider->driver()->createMailbox($provider, $emailAddress, $password);

        return $client->emailAccounts()->create([
            'email_provider_id' => $provider->id,
            'email_address' => $emailAddress,
            'status' => EmailAccountStatus::Activa,
            'provisioned_at' => now(),
        ]);
    }
}
