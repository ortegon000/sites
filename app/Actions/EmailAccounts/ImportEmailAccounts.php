<?php

namespace App\Actions\EmailAccounts;

use App\Enums\EmailAccountOrigin;
use App\Enums\EmailAccountStatus;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailProvider;
use Illuminate\Support\Collection;
use RuntimeException;

class ImportEmailAccounts
{
    /**
     * Register mailboxes that already exist on the provider. Nothing is created
     * remotely — the mailbox is already there — so no password is captured
     * either; staff can set one afterwards on providers that keep it locally.
     *
     * @param  array<int, string>  $emailAddresses
     * @return Collection<int, EmailAccount>
     */
    public function handle(Domain $domain, EmailProvider $provider, array $emailAddresses): Collection
    {
        if (! $domain->managesEmail()) {
            throw new RuntimeException("El dominio [{$domain->name}] no tiene el correo activado.");
        }

        return collect($emailAddresses)
            ->filter(fn (string $address) => str_ends_with($address, '@'.$domain->name))
            ->reject(fn (string $address) => EmailAccount::where('email_address', $address)->exists())
            ->map(fn (string $address) => $domain->emailAccounts()->create([
                'email_provider_id' => $provider->id,
                'email_address' => $address,
                'password' => null,
                'origin' => EmailAccountOrigin::Imported,
                'status' => EmailAccountStatus::Activa,
                'provisioned_at' => null,
            ]))
            ->values();
    }
}
