<?php

namespace Database\Seeders;

use App\Actions\EmailAccounts\ProvisionEmailAccount;
use App\Enums\ClientType;
use App\Enums\EmailProviderDriverType;
use App\Enums\EmailProviderStatus;
use App\Models\Client;
use App\Models\EmailProvider;
use Illuminate\Database\Seeder;

class EmailProviderSeeder extends Seeder
{
    /**
     * Seed a demo (simulated) email provider and provision a couple of
     * mailboxes for existing clients, to show the Fase 5 scaffolding
     * working end to end without any real provider credentials yet.
     */
    public function run(): void
    {
        $provider = EmailProvider::factory()->create([
            'name' => 'MXroute (simulado)',
            'driver' => EmailProviderDriverType::NullDriver,
            'status' => EmailProviderStatus::Activo,
        ]);

        $clients = Client::where('type', ClientType::Client)->take(2)->get();

        foreach ($clients as $client) {
            $localPart = str($client->name)->slug('.');

            app(ProvisionEmailAccount::class)->handle(
                $client,
                $provider,
                "{$localPart}@ejemplo-cliente.test",
                'password-temporal',
            );
        }
    }
}
