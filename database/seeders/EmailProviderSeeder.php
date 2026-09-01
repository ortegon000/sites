<?php

namespace Database\Seeders;

use App\Actions\EmailAccounts\ProvisionEmailAccount;
use App\Enums\DomainEmailManagement;
use App\Enums\DomainManagement;
use App\Enums\DomainStatus;
use App\Enums\EmailProviderDriverType;
use App\Enums\EmailProviderStatus;
use App\Enums\ProjectType;
use App\Models\Domain;
use App\Models\EmailProvider;
use App\Models\Project;
use Illuminate\Database\Seeder;

class EmailProviderSeeder extends Seeder
{
    /**
     * Seed two demo providers — one simulated (stands in for an API-backed
     * provider) and one manual (administered by hand, so it keeps mailbox
     * passwords locally) — give a couple of existing projects a domain, and
     * provision a mailbox on each, so the domain → mailbox chain is visible
     * end to end without real provider credentials.
     */
    public function run(): void
    {
        $provider = EmailProvider::factory()->create([
            'name' => 'MXroute (simulado)',
            'driver' => EmailProviderDriverType::NullDriver,
            'status' => EmailProviderStatus::Activo,
        ]);

        $manualProvider = EmailProvider::factory()->create([
            'name' => 'Proveedor manual (sin API)',
            'driver' => EmailProviderDriverType::Manual,
            'status' => EmailProviderStatus::Activo,
            'connection_settings' => [
                'imap_host' => 'imap.proveedor-manual.test',
                'imap_port' => '993',
                'smtp_host' => 'smtp.proveedor-manual.test',
                'smtp_port' => '587',
            ],
        ]);

        $projects = Project::with('client')->take(2)->get();

        foreach ($projects as $project) {
            $project->update([
                'type' => ProjectType::Web,
                'includes_email' => true,
            ]);

            $domain = Domain::create([
                'client_id' => $project->client_id,
                'project_id' => $project->id,
                'name' => str($project->client->name)->slug().'.test',
                'management' => DomainManagement::Managed,
                'registrar' => 'Namecheap',
                'registered_at' => now()->subYear(),
                'expires_at' => now()->addDays(20),
                'auto_renew' => true,
                'email_management' => DomainEmailManagement::Managed,
                'status' => DomainStatus::Activo,
            ]);

            app(ProvisionEmailAccount::class)->handle(
                $domain,
                $provider,
                'contacto@'.$domain->name,
                'password-temporal',
            );

            app(ProvisionEmailAccount::class)->handle(
                $domain,
                $manualProvider,
                'administracion@'.$domain->name,
                'password-manual',
            );
        }
    }
}
