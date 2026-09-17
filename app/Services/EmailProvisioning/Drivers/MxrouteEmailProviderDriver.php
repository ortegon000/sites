<?php

namespace App\Services\EmailProvisioning\Drivers;

use App\Models\EmailProvider;
use App\Services\EmailProvisioning\Contracts\EmailProviderDriver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the real MXroute REST API (api.mxroute.com), a thin layer over
 * DirectAdmin. Every mailbox operation is scoped to a domain, and
 * authentication is three headers read from the provider's own credentials:
 * the mail server hostname, the DirectAdmin username, and an API key
 * generated at panel.mxroute.com/api-keys.php.
 */
class MxrouteEmailProviderDriver implements EmailProviderDriver
{
    public function createMailbox(EmailProvider $provider, string $emailAddress, ?string $password): void
    {
        if ($password === null) {
            throw new RuntimeException('MXroute necesita una contraseña para crear el buzón.');
        }

        [$user, $domain] = $this->split($emailAddress);

        $this->client($provider)
            ->post("/domains/{$domain}/email-accounts", [
                'username' => $user,
                'password' => $password,
            ])
            ->throw();
    }

    public function deleteMailbox(EmailProvider $provider, string $emailAddress): void
    {
        [$user, $domain] = $this->split($emailAddress);

        $this->client($provider)
            ->delete("/domains/{$domain}/email-accounts/{$user}")
            ->throw();
    }

    public function changePassword(EmailProvider $provider, string $emailAddress, string $password): void
    {
        [$user, $domain] = $this->split($emailAddress);

        $this->client($provider)
            ->patch("/domains/{$domain}/email-accounts/{$user}", [
                'password' => $password,
            ])
            ->throw();
    }

    public function listMailboxes(EmailProvider $provider, string $domain): array
    {
        $response = $this->client($provider)
            ->get("/domains/{$domain}/email-accounts")
            ->throw();

        return array_column($response->json('data', []), 'email');
    }

    public function getConnectionSettings(EmailProvider $provider, ?string $domain = null): array
    {
        $server = $provider->credentials['server'] ?? '';

        return [
            'imap_host' => $server,
            'imap_port' => '993',
            'smtp_host' => $server,
            'smtp_port' => '587',
        ];
    }

    private function client(EmailProvider $provider): PendingRequest
    {
        $credentials = $provider->credentials ?? [];

        if (empty($credentials['server']) || empty($credentials['username']) || empty($credentials['api_key'])) {
            throw new RuntimeException("El proveedor [{$provider->name}] no tiene credenciales de MXroute completas.");
        }

        return Http::baseUrl(config('services.mxroute.base_url'))
            ->withHeaders([
                'X-Server' => $credentials['server'],
                'X-Username' => $credentials['username'],
                'X-API-Key' => $credentials['api_key'],
            ])
            ->acceptJson();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $emailAddress): array
    {
        return explode('@', $emailAddress, 2);
    }
}
