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
 * @return array{0: Project, 1: Domain}
 */
function projectWithEmailDomain(bool $includesEmail = true): array
{
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create(['includes_email' => $includesEmail]);
    $domain = Domain::factory()->for($client)->for($project)->withManagedEmail()->create();

    return [$project, $domain];
}

test('staff can add a domain to a project', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create(['includes_email' => true]);

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('openDomainModal')
        ->set('domainName', 'acme.com')
        ->set('emailManagement', DomainEmailManagement::Managed->value)
        ->call('saveDomain')
        ->assertHasNoErrors();

    expect($project->domains()->where('name', 'acme.com')->exists())->toBeTrue()
        ->and($project->domains()->first()->client_id)->toBe($project->client_id);
});

test('a domain cannot manage email when its project does not include email', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create(['includes_email' => false]);

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('openDomainModal')
        ->set('domainName', 'acme.com')
        ->set('emailManagement', DomainEmailManagement::Managed->value)
        ->call('saveDomain')
        ->assertHasErrors('emailManagement');

    expect($project->domains()->count())->toBe(0);
});

test('staff can provision an email account on a domain', function () {
    $staff = User::factory()->staff()->create();
    [$project, $domain] = projectWithEmailDomain();
    $provider = EmailProvider::factory()->create();

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $project->client, 'project' => $project])
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
    [$project] = projectWithEmailDomain();
    $domain = Domain::factory()->for($project->client)->for($project)->create([
        'email_management' => DomainEmailManagement::NotManaged,
    ]);
    $provider = EmailProvider::factory()->create();

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $project->client, 'project' => $project])
        ->set('emailDomainId', $domain->id)
        ->set('emailProviderIdToAssign', $provider->id)
        ->set('newEmailAddress', 'nueva@cliente.test')
        ->set('newEmailPassword', 'password123')
        ->call('provisionEmailAccount')
        ->assertHasErrors('emailDomainId');

    expect($domain->emailAccounts()->count())->toBe(0);
});

test('a mailbox on a simulated provider never stores its password', function () {
    [$project, $domain] = projectWithEmailDomain();
    $provider = EmailProvider::factory()->create();

    $emailAccount = app(ProvisionEmailAccount::class)
        ->handle($domain, $provider, 'sin-password@cliente.test', 'password123');

    expect($emailAccount->password)->toBeNull();
});

test('staff can delete an email account', function () {
    $staff = User::factory()->staff()->create();
    [$project, $domain] = projectWithEmailDomain();
    $emailAccount = EmailAccount::factory()->for($domain)->create();

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('deleteEmailAccount', $emailAccount->id);

    expect(EmailAccount::find($emailAccount->id))->toBeNull();
});

test('staff cannot act on a mailbox belonging to another client', function () {
    $staff = User::factory()->staff()->create();
    [$project] = projectWithEmailDomain();
    [, $otherDomain] = projectWithEmailDomain();
    $foreignAccount = EmailAccount::factory()->for($otherDomain)->create();

    $this->actingAs($staff);

    expect(fn () => Livewire::test(DomainsPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('deleteEmailAccount', $foreignAccount->id))
        ->toThrow(ModelNotFoundException::class);

    expect(EmailAccount::find($foreignAccount->id))->not->toBeNull();
});

test('staff can change an email account password', function () {
    $staff = User::factory()->staff()->create();
    [$project, $domain] = projectWithEmailDomain();
    $emailAccount = EmailAccount::factory()->for($domain)->create(['status' => EmailAccountStatus::Activa]);

    $this->actingAs($staff);

    Livewire::test(DomainsPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('openPasswordModal', $emailAccount->id)
        ->set('newPassword', 'nueva-password')
        ->call('changePassword')
        ->assertHasNoErrors();

    expect($emailAccount->refresh()->status)->toBe(EmailAccountStatus::Activa);
});

test('a domain with no project can be managed from the client detail', function () {
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

    expect($domain->project_id)->toBeNull()
        ->and($domain->hosting_plan)->toBe('compartido')
        ->and($domain->site_url)->toBe('https://solo-hosting.test');
});

test('collaborator does not see the domains card on a project', function () {
    $collaborator = User::factory()->collaborator()->create();
    [$project] = projectWithEmailDomain();
    $project->users()->attach($collaborator);

    $this->actingAs($collaborator);

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSee('Dominios y correo');
});

test('staff can import mailboxes that already exist on the provider', function () {
    $staff = User::factory()->staff()->create();
    [$project, $domain] = projectWithEmailDomain();
    $provider = EmailProvider::factory()->create();

    EmailAccount::factory()->for($domain)->create([
        'email_address' => 'info@'.$domain->name,
        'email_provider_id' => $provider->id,
    ]);

    $this->actingAs($staff);

    $component = Livewire::test(DomainsPanel::class, ['client' => $project->client, 'project' => $project])
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
    [$project, $domain] = projectWithEmailDomain();
    $provider = EmailProvider::factory()->create();

    $imported = app(ImportEmailAccounts::class)->handle($domain, $provider, [
        'valido@'.$domain->name,
        'ajeno@otrodominio.test',
    ]);

    expect($imported)->toHaveCount(1)
        ->and($imported->first()->email_address)->toBe('valido@'.$domain->name);
});

test('a manual provider stores the mailbox password and serves its own connection settings', function () {
    [$project, $domain] = projectWithEmailDomain();
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
    [$project, $domain] = projectWithEmailDomain();
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
