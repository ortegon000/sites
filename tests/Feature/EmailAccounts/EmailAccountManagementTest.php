<?php

use App\Actions\EmailAccounts\ChangeEmailAccountPassword;
use App\Actions\EmailAccounts\ImportEmailAccounts;
use App\Actions\EmailAccounts\ProvisionEmailAccount;
use App\Enums\DomainEmailManagement;
use App\Enums\EmailAccountOrigin;
use App\Enums\EmailAccountStatus;
use App\Livewire\DomainsPanel;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailProvider;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Un cliente con un dominio que sí administramos: el correo cuelga del dominio
 * y del cliente, sin ningún proyecto de por medio.
 *
 * @return array{0: Client, 1: Domain}
 */
function clientWithEmailDomain(): array
{
    $client = Client::factory()->client()->create();
    $domain = Domain::factory()->for($client)->withManagedEmail()->create();

    return [$client, $domain];
}

test('staff can add a domain to a client', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('openDomainModal')
        ->set('domainName', 'acme.com')
        ->set('emailManagement', DomainEmailManagement::Managed->value)
        ->call('saveDomain')
        ->assertHasNoErrors();

    expect($client->domains()->where('name', 'acme.com')->exists())->toBeTrue();
});

test('el alta de dominio no propone correo administrado: eso lo decide el dominio', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('openDomainModal')
        ->assertSet('emailManagement', DomainEmailManagement::NotManaged->value);
});

test('a hosting-only domain can manage its mailboxes', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();
    $provider = EmailProvider::factory()->create();

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('openDomainModal')
        ->set('domainName', 'solo-hosting.test')
        ->set('emailManagement', DomainEmailManagement::Managed->value)
        ->call('saveDomain')
        ->assertHasNoErrors();

    $domain = $client->domains()->firstOrFail();

    expect($domain->managesEmail())->toBeTrue();

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('openEmailModal', $domain->id)
        ->set('emailProviderIdToAssign', $provider->id)
        ->set('newEmailAddress', 'hola@solo-hosting.test')
        ->set('newEmailPassword', 'password123')
        ->call('provisionEmailAccount')
        ->assertHasNoErrors();

    expect($domain->emailAccounts()->count())->toBe(1);
});

test('staff can provision an email account on a domain', function () {
    $staff = User::factory()->staff()->create();
    [$client, $domain] = clientWithEmailDomain();
    $provider = EmailProvider::factory()->create();

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('openEmailModal', $domain->id)
        ->set('emailProviderIdToAssign', $provider->id)
        ->set('newEmailAddress', 'nueva@cliente.test')
        ->set('newEmailPassword', 'password123')
        ->call('provisionEmailAccount')
        ->assertHasNoErrors();

    expect($domain->emailAccounts()->where('email_address', 'nueva@cliente.test')->exists())->toBeTrue();
});

test('provisioning is rejected on a domain that does not manage email', function () {
    $staff = User::factory()->staff()->create();
    [$client] = clientWithEmailDomain();
    $domain = Domain::factory()->for($client)->create([
        'email_management' => DomainEmailManagement::NotManaged,
    ]);
    $provider = EmailProvider::factory()->create();

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->set('emailDomainId', $domain->id)
        ->set('emailProviderIdToAssign', $provider->id)
        ->set('newEmailAddress', 'nueva@cliente.test')
        ->set('newEmailPassword', 'password123')
        ->call('provisionEmailAccount')
        ->assertHasErrors('emailDomainId');

    expect($domain->emailAccounts()->count())->toBe(0);
});

test('a mailbox on a simulated provider never stores its password', function () {
    [$client, $domain] = clientWithEmailDomain();
    $provider = EmailProvider::factory()->create();

    $emailAccount = app(ProvisionEmailAccount::class)
        ->handle($domain, $provider, 'sin-password@cliente.test', 'password123');

    expect($emailAccount->password)->toBeNull();
});

test('staff can delete an email account', function () {
    $staff = User::factory()->staff()->create();
    [$client, $domain] = clientWithEmailDomain();
    $emailAccount = EmailAccount::factory()->for($domain)->create();

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('deleteEmailAccount', $emailAccount->id);

    expect(EmailAccount::find($emailAccount->id))->toBeNull();
});

test('staff cannot act on a mailbox belonging to another client', function () {
    $staff = User::factory()->staff()->create();
    [$client] = clientWithEmailDomain();
    [, $otherDomain] = clientWithEmailDomain();
    $foreignAccount = EmailAccount::factory()->for($otherDomain)->create();

    $this->actingAs($staff);

    expect(fn () => Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('deleteEmailAccount', $foreignAccount->id))
        ->toThrow(ModelNotFoundException::class);

    expect(EmailAccount::find($foreignAccount->id))->not->toBeNull();
});

test('staff can change an email account password', function () {
    $staff = User::factory()->staff()->create();
    [$client, $domain] = clientWithEmailDomain();
    $emailAccount = EmailAccount::factory()->for($domain)->create(['status' => EmailAccountStatus::Activa]);

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('openPasswordModal', $emailAccount->id)
        ->set('newPassword', 'nueva-password')
        ->call('changePassword')
        ->assertHasNoErrors();

    expect($emailAccount->refresh()->status)->toBe(EmailAccountStatus::Activa);
});

test('el dominio se administra desde la ficha del cliente', function () {
    $staff = User::factory()->staff()->create();
    $client = Client::factory()->client()->create();

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('openDomainModal')
        ->set('domainName', 'solo-hosting.test')
        ->set('hostingPlan', 'compartido')
        ->set('siteUrl', 'https://solo-hosting.test')
        ->call('saveDomain')
        ->assertHasNoErrors();

    $domain = $client->domains()->firstOrFail();

    expect($domain->hosting_plan)->toBe('compartido')
        ->and($domain->site_url)->toBe('https://solo-hosting.test');
});

test('el detalle del proyecto ya no muestra la tarjeta de dominios', function () {
    $staff = User::factory()->staff()->create();
    [$client, $domain] = clientWithEmailDomain();
    $project = Project::factory()->for($client)->create();

    $this->actingAs($staff);

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSee('Dominios y correo')
        ->assertDontSee($domain->name);
});

test('staff can import mailboxes that already exist on the provider', function () {
    $staff = User::factory()->staff()->create();
    [$client, $domain] = clientWithEmailDomain();
    $provider = EmailProvider::factory()->create();

    EmailAccount::factory()->for($domain)->create([
        'email_address' => 'info@'.$domain->name,
        'email_provider_id' => $provider->id,
    ]);

    $this->actingAs($staff);

    $component = Livewire::test(DomainsPanel::class, ['client' => $client])
        ->call('openImportModal', $domain->id)
        ->set('importProviderId', $provider->id)
        ->call('loadImportCandidates');

    $candidates = $component->get('importCandidates');

    expect($candidates)->toContain('ventas@'.$domain->name)
        ->and($candidates)->not->toContain('info@'.$domain->name);

    $component
        ->set('selectedImports', ['ventas@'.$domain->name])
        ->call('importEmailAccounts')
        ->assertHasNoErrors();

    $imported = $domain->emailAccounts()->where('email_address', 'ventas@'.$domain->name)->first();

    expect($imported->origin)->toBe(EmailAccountOrigin::Imported)
        ->and($imported->provisioned_at)->toBeNull()
        ->and($imported->password)->toBeNull();
});

test('importing rejects addresses that do not belong to the domain', function () {
    [$client, $domain] = clientWithEmailDomain();
    $provider = EmailProvider::factory()->create();

    $imported = app(ImportEmailAccounts::class)->handle($domain, $provider, [
        'valido@'.$domain->name,
        'ajeno@otrodominio.test',
    ]);

    expect($imported)->toHaveCount(1)
        ->and($imported->first()->email_address)->toBe('valido@'.$domain->name);
});

test('a manual provider stores the mailbox password and serves its own connection settings', function () {
    [$client, $domain] = clientWithEmailDomain();
    $provider = EmailProvider::factory()->manual()->create([
        'connection_settings' => [
            'imap_host' => 'imap.miproveedor.com',
            'imap_port' => '993',
            'smtp_host' => 'smtp.miproveedor.com',
            'smtp_port' => '587',
        ],
    ]);

    $emailAccount = app(ProvisionEmailAccount::class)
        ->handle($domain, $provider, 'manual@'.$domain->name, 'password123');

    expect($emailAccount->password)->toBe('password123')
        ->and($provider->driver()->getConnectionSettings($provider)['imap_host'])->toBe('imap.miproveedor.com');

    app(ChangeEmailAccountPassword::class)->handle($emailAccount, 'otra-password');

    expect($emailAccount->refresh()->password)->toBe('otra-password');
});

test('a stored password is never written in plain text to the database', function () {
    [$client, $domain] = clientWithEmailDomain();
    $provider = EmailProvider::factory()->manual()->create();

    $emailAccount = app(ProvisionEmailAccount::class)
        ->handle($domain, $provider, 'cifrada@'.$domain->name, 'password123');

    $raw = DB::table('email_accounts')->where('id', $emailAccount->id)->value('password');

    expect($raw)->not->toBe('password123')
        ->and($emailAccount->fresh()->password)->toBe('password123');
});

test('a manual provider derives its connection settings from each domain', function () {
    $provider = EmailProvider::factory()->manual()->create([
        'connection_settings' => [
            'imap_host' => 'mail.{dominio}',
            'imap_port' => '993',
            'smtp_host' => 'mail.{dominio}',
            'smtp_port' => '587',
            'webmail_url' => 'https://webmail.{dominio}',
        ],
    ]);

    $settings = $provider->driver()->getConnectionSettings($provider, 'acme.com');

    expect($settings['imap_host'])->toBe('mail.acme.com')
        ->and($settings['smtp_host'])->toBe('mail.acme.com')
        ->and($settings['webmail_url'])->toBe('https://webmail.acme.com')
        ->and($settings['imap_port'])->toBe('993');
});

test('without a domain the template is left as captured', function () {
    $provider = EmailProvider::factory()->manual()->create([
        'connection_settings' => ['imap_host' => 'mail.{dominio}'],
    ]);

    expect($provider->driver()->getConnectionSettings($provider)['imap_host'])->toBe('mail.{dominio}');
});
