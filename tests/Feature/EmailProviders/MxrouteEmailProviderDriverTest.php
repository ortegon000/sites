<?php

use App\Models\EmailProvider;
use App\Services\EmailProvisioning\Drivers\MxrouteEmailProviderDriver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function mxrouteProvider(): EmailProvider
{
    return EmailProvider::factory()->mxroute()->create();
}

test('creates a mailbox through the mxroute api', function () {
    Http::fake([
        'api.mxroute.com/domains/acme.com/email-accounts' => Http::response(['success' => true], 201),
    ]);

    $provider = mxrouteProvider();

    (new MxrouteEmailProviderDriver)->createMailbox($provider, 'ventas@acme.com', 'S3cret123');

    Http::assertSent(function ($request) use ($provider) {
        return $request->url() === 'https://api.mxroute.com/domains/acme.com/email-accounts'
            && $request->method() === 'POST'
            && $request['username'] === 'ventas'
            && $request['password'] === 'S3cret123'
            && $request->hasHeader('X-Server', $provider->credentials['server'])
            && $request->hasHeader('X-Username', $provider->credentials['username'])
            && $request->hasHeader('X-API-Key', $provider->credentials['api_key']);
    });
});

test('deletes a mailbox through the mxroute api', function () {
    Http::fake([
        'api.mxroute.com/domains/acme.com/email-accounts/ventas' => Http::response(null, 204),
    ]);

    (new MxrouteEmailProviderDriver)->deleteMailbox(mxrouteProvider(), 'ventas@acme.com');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request->url() === 'https://api.mxroute.com/domains/acme.com/email-accounts/ventas');
});

test('changes a mailbox password through the mxroute api', function () {
    Http::fake([
        'api.mxroute.com/domains/acme.com/email-accounts/ventas' => Http::response(['success' => true], 200),
    ]);

    (new MxrouteEmailProviderDriver)->changePassword(mxrouteProvider(), 'ventas@acme.com', 'NewS3cret1');

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request['password'] === 'NewS3cret1');
});

test('lists mailboxes for a domain through the mxroute api', function () {
    Http::fake([
        'api.mxroute.com/domains/acme.com/email-accounts' => Http::response([
            'success' => true,
            'data' => [
                ['username' => 'info', 'email' => 'info@acme.com'],
                ['username' => 'ventas', 'email' => 'ventas@acme.com'],
            ],
        ], 200),
    ]);

    $mailboxes = (new MxrouteEmailProviderDriver)->listMailboxes(mxrouteProvider(), 'acme.com');

    expect($mailboxes)->toBe(['info@acme.com', 'ventas@acme.com']);
});

test('resolves imap and smtp settings from the stored server hostname', function () {
    $provider = EmailProvider::factory()->mxroute()->create([
        'credentials' => ['server' => 'eagle.mxlogin.com', 'username' => 'johndoe', 'api_key' => 'Mx123'],
    ]);

    $settings = (new MxrouteEmailProviderDriver)->getConnectionSettings($provider);

    expect($settings)->toBe([
        'imap_host' => 'eagle.mxlogin.com',
        'imap_port' => '993',
        'smtp_host' => 'eagle.mxlogin.com',
        'smtp_port' => '587',
    ]);
});

test('throws when the provider has incomplete mxroute credentials', function () {
    $provider = EmailProvider::factory()->mxroute()->create(['credentials' => ['server' => 'eagle.mxlogin.com']]);

    (new MxrouteEmailProviderDriver)->createMailbox($provider, 'ventas@acme.com', 'S3cret123');
})->throws(RuntimeException::class);

test('throws when the mxroute api responds with an error', function () {
    Http::fake([
        'api.mxroute.com/*' => Http::response(['success' => false, 'error' => ['code' => 'CONFLICT']], 409),
    ]);

    (new MxrouteEmailProviderDriver)->createMailbox(mxrouteProvider(), 'ventas@acme.com', 'S3cret123');
})->throws(RequestException::class);
