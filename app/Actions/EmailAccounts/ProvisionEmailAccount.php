<?php

namespace App\Actions\EmailAccounts;

use App\Enums\EmailAccountOrigin;
use App\Enums\EmailAccountStatus;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailProvider;
use RuntimeException;

class ProvisionEmailAccount
{
    public function handle(Domain $domain, EmailProvider $provider, string $emailAddress, string $password): EmailAccount
    {
        if (! $domain->managesEmail()) {
            throw new RuntimeException("El dominio [{$domain->name}] no tiene el correo activado.");
        }

        $provider->driver()->createMailbox($provider, $emailAddress, $password);

        return $domain->emailAccounts()->create([
            'email_provider_id' => $provider->id,
            'email_address' => $emailAddress,
            'password' => $provider->storesPasswordLocally() ? $password : null,
            'origin' => EmailAccountOrigin::Provisioned,
            'status' => EmailAccountStatus::Activa,
            'provisioned_at' => now(),
        ]);
    }
}
