<?php

namespace Database\Seeders;

use App\Actions\Clients\SyncClientAgencyToProjects;
use App\Enums\AdBudgetBilling;
use App\Enums\AdCampaignStatus;
use App\Enums\AdPlatform;
use App\Enums\AgencyBillingDirection;
use App\Enums\ChargeStatus;
use App\Enums\DomainEmailManagement;
use App\Enums\DomainManagement;
use App\Enums\DomainStatus;
use App\Enums\EmailAccountOrigin;
use App\Enums\EmailAccountStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Enums\UserRole;
use App\Models\AdCampaign;
use App\Models\Agency;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailProvider;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceInstallment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Construye el grafo completo de proyectos: servicios, cobros, cuotas,
 * dominios, buzones, campañas, equipo y agencias.
 *
 * Está escrito como escenarios con nombre en vez de bucles sobre factories
 * porque su trabajo es cubrir casos, no llenar tablas: cada proyecto ejercita
 * una combinación distinta de tipo, frecuencia de cobro, estatus y proveedor,
 * de modo que abrir la app muestre el sistema completo sin tener que capturar
 * nada a mano ni correr `charges:process` primero.
 *
 * Los servicios recurrentes activos quedan con `next_charge_date` en el futuro
 * a propósito: así una corrida del comando no genera de inmediato una tanda de
 * cobros duplicados sobre los que ya están sembrados.
 */
class ProjectSeeder extends Seeder
{
    private User $staff;

    private ?User $collaborator;

    private EmailProvider $apiProvider;

    private EmailProvider $manualProvider;

    public function run(): void
    {
        $this->staff = User::where('role', UserRole::Staff)->firstOrFail();
        $this->collaborator = User::where('role', UserRole::Collaborator)->first();
        $this->apiProvider = EmailProvider::where('name', 'MXroute (simulado)')->firstOrFail();
        $this->manualProvider = EmailProvider::where('name', 'Proveedor manual (sin API)')->firstOrFail();

        $this->seedWebProject();
        $this->seedMaintenanceProject();
        $this->seedAdsProject();
        $this->seedEmailProject();
        $this->seedCompletedInstallmentProject();
        $this->seedCancelledProject();
        $this->seedDomainWithoutProject();
    }

    /**
     * El caso completo: sitio web de pago único más hosting, SSL, correo y
     * dominio anuales, con su dominio administrado y buzones de ambos tipos de
     * proveedor. El dominio expira en 20 días, así que dispara el recordatorio
     * de renovación en la siguiente corrida de `charges:process`.
     */
    private function seedWebProject(): void
    {
        $client = Client::where('name', 'Cliente Demo')->firstOrFail();

        $project = Project::factory()->for($client)->create([
            'name' => 'Sitio web Cliente Demo',
            'description' => 'Sitio institucional con blog y formulario de contacto.',
            'type' => ProjectType::Web,
            'includes_email' => true,
            'status' => ProjectStatus::Activo,
            'started_at' => now()->subMonths(14)->toDateString(),
        ]);

        $project->users()->attach(array_filter([$this->staff->id, $this->collaborator?->id]));

        $project->agencies()->attach(Agency::where('name', 'Pixel Forge Studio')->firstOrFail(), [
            'billing_direction' => AgencyBillingDirection::WeInvoiceThem,
            'notes' => 'Subcontratamos el diseño de este sitio.',
        ]);

        $domain = Domain::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'cliente-demo.test',
            'management' => DomainManagement::Managed,
            'registrar' => 'Namecheap',
            'registered_at' => now()->subYears(3)->toDateString(),
            'expires_at' => now()->addDays(20)->toDateString(),
            'auto_renew' => true,
            'email_management' => DomainEmailManagement::Managed,
            'status' => DomainStatus::Activo,
        ]);

        $website = $this->service($project, 'Sitio web', ServiceCategory::Website, ServiceBillingFrequency::OneTime, '45000.00', [
            'status' => ServiceStatus::Completado,
            'next_charge_date' => null,
        ]);
        Charge::factory()->for($website)->paid()->create(['amount' => '45000.00']);

        $hosting = $this->service($project, 'Hosting', ServiceCategory::Hosting, ServiceBillingFrequency::Annual, '3800.00', ['domain_id' => $domain->id]);
        Charge::factory()->for($hosting)->paid()->create(['amount' => '3800.00']);
        Charge::factory()->for($hosting)->dueSoon()->create(['amount' => '3800.00']);

        $ssl = $this->service($project, 'Certificado SSL', ServiceCategory::Ssl, ServiceBillingFrequency::Annual, '1200.00', ['domain_id' => $domain->id]);
        Charge::factory()->for($ssl)->overdue()->create(['amount' => '1200.00']);

        $email = $this->service($project, 'Correo', ServiceCategory::Email, ServiceBillingFrequency::Annual, '2400.00', ['domain_id' => $domain->id]);
        Charge::factory()->for($email)->pending()->create(['amount' => '2400.00']);

        $domainService = $this->service($project, 'Dominio', ServiceCategory::Domain, ServiceBillingFrequency::Annual, '450.00', ['domain_id' => $domain->id]);
        Charge::factory()->for($domainService)->dueSoon()->alreadyNotified()->create(['amount' => '450.00']);

        /** Buzón creado desde el CRM: el proveedor tiene API, así que no guarda contraseña. */
        $this->mailbox($domain, $this->apiProvider, 'contacto@cliente-demo.test', EmailAccountOrigin::Provisioned);

        /** Buzón que ya existía en el servidor y se vinculó desde la pantalla de importación. */
        $this->mailbox($domain, $this->apiProvider, 'ventas@cliente-demo.test', EmailAccountOrigin::Imported);

        /** Proveedor manual: sin API que resetee la contraseña, así que se guarda cifrada. */
        $this->mailbox($domain, $this->manualProvider, 'administracion@cliente-demo.test', EmailAccountOrigin::Provisioned, 'password-manual');

        /** Buzón suspendido, para que el badge de estatus tenga los dos valores. */
        $this->mailbox($domain, $this->manualProvider, 'antiguo@cliente-demo.test', EmailAccountOrigin::Imported, 'password-antigua', EmailAccountStatus::Suspendida);
    }

    /**
     * Mantenimiento trimestral, sin correo, con un dominio del que solo damos
     * seguimiento porque su correo lo administra el propio cliente.
     */
    private function seedMaintenanceProject(): void
    {
        $client = Client::where('name', 'Cliente Demo')->firstOrFail();

        $project = Project::factory()->for($client)->create([
            'name' => 'Mantenimiento Cliente Demo',
            'description' => 'Actualizaciones, respaldos y soporte trimestral.',
            'type' => ProjectType::Maintenance,
            'includes_email' => false,
            'status' => ProjectStatus::Activo,
            'started_at' => now()->subMonths(8)->toDateString(),
        ]);

        $project->users()->attach($this->staff);

        Domain::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'tienda-demo.test',
            'management' => DomainManagement::Tracked,
            'registrar' => null,
            'expires_at' => now()->addMonths(5)->toDateString(),
            'auto_renew' => false,
            'email_management' => DomainEmailManagement::NotManaged,
            'email_notes' => 'Google Workspace, lo administra el cliente.',
            'status' => DomainStatus::Activo,
        ]);

        $maintenance = $this->service($project, 'Mantenimiento web', ServiceCategory::Maintenance, ServiceBillingFrequency::Quarterly, '6500.00');
        Charge::factory()->for($maintenance)->paid()->create(['amount' => '6500.00']);
        Charge::factory()->for($maintenance)->pending()->create(['amount' => '6500.00']);

        /** Semestral y sin categoría propia: cierra el resto de la matriz. */
        $backups = $this->service($project, 'Respaldos externos', ServiceCategory::Other, ServiceBillingFrequency::Semiannual, '2800.00');
        Charge::factory()->for($backups)->paid()->create(['amount' => '2800.00']);
    }

    /**
     * Campañas de ads con las dos formas de facturar el presupuesto: una que
     * pasa por nosotros y genera su propio servicio de inversión, y otra que el
     * cliente paga directo a la plataforma y por eso no genera ningún cobro.
     */
    private function seedAdsProject(): void
    {
        $client = Client::where('name', 'Tacos El Güero')->firstOrFail();

        $project = Project::factory()->for($client)->create([
            'name' => 'Campañas Meta y Google',
            'description' => 'Adquisición para las sucursales del norte.',
            'type' => ProjectType::Ads,
            'includes_email' => false,
            'status' => ProjectStatus::Activo,
            'started_at' => now()->subMonths(5)->toDateString(),
        ]);

        if ($this->collaborator) {
            $project->users()->attach($this->collaborator);
        }

        $meta = AdCampaign::factory()->for($project)->create([
            'name' => 'Meta — remarketing',
            'platform' => AdPlatform::Meta,
            'ad_account_id' => '284917365',
            'objective' => 'Conversiones',
            'monthly_budget' => '18000.00',
            'budget_billing' => AdBudgetBilling::PassThrough,
            'status' => AdCampaignStatus::Activa,
            'starts_on' => now()->subMonths(5)->toDateString(),
        ]);

        AdCampaign::factory()->for($project)->create([
            'name' => 'Google — búsqueda de marca',
            'platform' => AdPlatform::Google,
            'ad_account_id' => '734-014-6150',
            'objective' => 'Tráfico',
            'monthly_budget' => '9000.00',
            'budget_billing' => AdBudgetBilling::ClientDirect,
            'status' => AdCampaignStatus::Activa,
            'starts_on' => now()->subMonths(3)->toDateString(),
        ]);

        AdCampaign::factory()->for($project)->create([
            'name' => 'TikTok — lanzamiento de sucursal',
            'platform' => AdPlatform::TikTok,
            'objective' => 'Reconocimiento',
            'monthly_budget' => '7000.00',
            'budget_billing' => AdBudgetBilling::ClientDirect,
            'status' => AdCampaignStatus::Finalizada,
            'starts_on' => now()->subMonths(4)->toDateString(),
            'ends_on' => now()->subMonth()->toDateString(),
        ]);

        $fee = $this->service($project, 'Gestión de campañas', ServiceCategory::AdsManagement, ServiceBillingFrequency::Monthly, '12000.00');
        Charge::factory()->for($fee)->paid()->create(['amount' => '12000.00']);
        Charge::factory()->for($fee)->pending()->create(['amount' => '12000.00']);

        $budget = $this->service($project, 'Inversión publicitaria · Meta — remarketing', ServiceCategory::AdsBudget, ServiceBillingFrequency::Monthly, '18000.00', [
            'ad_campaign_id' => $meta->id,
        ]);
        Charge::factory()->for($budget)->paid()->create(['amount' => '18000.00']);
        Charge::factory()->for($budget)->overdue()->create(['amount' => '18000.00']);
    }

    /**
     * Proyecto solo de correo: sin sitio ni mantenimiento, únicamente los
     * buzones de un dominio, todos en el proveedor manual.
     */
    private function seedEmailProject(): void
    {
        $client = Client::where('name', 'Inmobiliaria Norte')->firstOrFail();

        $project = Project::factory()->for($client)->create([
            'name' => 'Correo corporativo',
            'description' => 'Buzones del equipo de ventas.',
            'type' => ProjectType::Email,
            'includes_email' => true,
            'status' => ProjectStatus::Activo,
            'started_at' => now()->subYear()->toDateString(),
        ]);

        $domain = Domain::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'inmobiliaria-norte.test',
            'management' => DomainManagement::Managed,
            'registrar' => 'GoDaddy',
            'registered_at' => now()->subYears(2)->toDateString(),
            'expires_at' => now()->addMonths(8)->toDateString(),
            'auto_renew' => false,
            'email_management' => DomainEmailManagement::Managed,
            'status' => DomainStatus::Activo,
        ]);

        $service = $this->service($project, 'Correo', ServiceCategory::Email, ServiceBillingFrequency::Annual, '3600.00', ['domain_id' => $domain->id]);
        Charge::factory()->for($service)->paid()->create(['amount' => '3600.00']);

        foreach (['direccion', 'ventas', 'administracion'] as $localPart) {
            $this->mailbox($domain, $this->manualProvider, "{$localPart}@inmobiliaria-norte.test", EmailAccountOrigin::Provisioned, "password-{$localPart}");
        }
    }

    /**
     * Rediseño pagado a plazos, ya terminado y en dólares. Lleva además un
     * servicio pausado, que existe para comprobar que un servicio no activo
     * deja de generar cobros.
     */
    private function seedCompletedInstallmentProject(): void
    {
        $client = Client::where('name', 'Estudio Marea')->firstOrFail();

        $project = Project::factory()->for($client)->create([
            'name' => 'Rediseño en pagos',
            'description' => 'Rediseño completo cobrado en cuatro exhibiciones.',
            'type' => ProjectType::Web,
            'includes_email' => false,
            'status' => ProjectStatus::Completado,
            'started_at' => now()->subMonths(7)->toDateString(),
            'ended_at' => now()->subMonth()->toDateString(),
        ]);

        $project->agencies()->attach(Agency::where('name', 'Northwind Digital')->firstOrFail(), [
            'billing_direction' => AgencyBillingDirection::TheyInvoiceUs,
            'notes' => 'Ellos nos subcontrataron para este rediseño.',
        ]);

        $redesign = $this->service($project, 'Rediseño', ServiceCategory::Website, ServiceBillingFrequency::Installment, '1500.00', [
            'currency' => 'USD',
            'status' => ServiceStatus::Completado,
            'next_charge_date' => null,
            'installments_count' => 4,
            'starts_on' => now()->subMonths(7)->toDateString(),
        ]);

        foreach (range(1, 4) as $number) {
            $installment = ServiceInstallment::factory()->for($redesign)->create([
                'installment_number' => $number,
                'amount' => '1500.00',
                /** Las tres primeras ya vencieron; la última sigue por venir, para que
                 *  quede pendiente de verdad y `charges:process` no la marque vencida. */
                'due_date' => now()->addMonths($number * 2 - 7)->toDateString(),
            ]);

            $status = match ($number) {
                1, 2 => ChargeStatus::Pagado,
                3 => ChargeStatus::Vencido,
                default => ChargeStatus::Pendiente,
            };

            Charge::factory()->for($redesign)->create([
                'service_installment_id' => $installment->id,
                'amount' => '1500.00',
                'currency' => 'USD',
                'status' => $status,
                'due_date' => $installment->due_date,
                'paid_at' => $status === ChargeStatus::Pagado ? $installment->due_date : null,
            ]);
        }

        $this->service($project, 'Hosting', ServiceCategory::Hosting, ServiceBillingFrequency::Annual, '95.00', [
            'currency' => 'USD',
            'status' => ServiceStatus::Pausado,
        ]);
    }

    /**
     * Proyecto cancelado de un cliente que llegó por agencia: la agencia se
     * hereda del cliente y queda sin dirección de facturación, esperando a que
     * el staff la defina. Su servicio cancelado tiene un cobro pagado, así que
     * no se puede borrar — solo cancelar.
     */
    private function seedCancelledProject(): void
    {
        $client = Client::where('name', 'Clínica Sur')->firstOrFail();

        $project = Project::factory()->for($client)->create([
            'name' => 'Landing de campaña',
            'description' => 'Landing de una campaña que no se renovó.',
            'type' => ProjectType::Other,
            'includes_email' => false,
            'status' => ProjectStatus::Cancelado,
            'started_at' => now()->subMonths(10)->toDateString(),
            'ended_at' => now()->subMonths(6)->toDateString(),
        ]);

        $project->users()->attach($this->staff);

        Domain::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'campana-clinica-sur.test',
            'management' => DomainManagement::Managed,
            'registrar' => 'Namecheap',
            'registered_at' => now()->subYears(2)->toDateString(),
            'expires_at' => now()->subMonths(2)->toDateString(),
            'auto_renew' => false,
            'email_management' => DomainEmailManagement::NotManaged,
            'status' => DomainStatus::Expirado,
        ]);

        $service = $this->service($project, 'Landing', ServiceCategory::Website, ServiceBillingFrequency::OneTime, '18000.00', [
            'status' => ServiceStatus::Cancelado,
            'next_charge_date' => null,
        ]);
        Charge::factory()->for($service)->paid()->create(['amount' => '18000.00']);

        $client->update(['agency_id' => Agency::where('name', 'Northwind Digital')->firstOrFail()->id]);
        app(SyncClientAgencyToProjects::class)->handle($client);
    }

    /**
     * Un dominio del cliente que no está ligado a ningún proyecto nuestro. Al
     * expirar solo alerta a los admins, porque no tiene equipo asignado.
     */
    private function seedDomainWithoutProject(): void
    {
        Domain::create([
            'client_id' => Client::where('name', 'Clínica Sur')->firstOrFail()->id,
            'project_id' => null,
            'name' => 'clinica-sur.test',
            'management' => DomainManagement::Tracked,
            'expires_at' => now()->addDays(12)->toDateString(),
            'auto_renew' => true,
            'email_management' => DomainEmailManagement::NotManaged,
            'email_notes' => 'El correo lo lleva su proveedor de sistemas.',
            'status' => DomainStatus::Activo,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function service(Project $project, string $name, ServiceCategory $category, ServiceBillingFrequency $frequency, string $amount, array $overrides = []): Service
    {
        return Service::factory()->for($project)->create(array_merge([
            'name' => $name,
            'category' => $category,
            'billing_frequency' => $frequency,
            'amount' => $amount,
            'currency' => $project->client->currency,
            'status' => ServiceStatus::Activo,
            'starts_on' => $project->started_at?->toDateString() ?? now()->toDateString(),
            'next_charge_date' => $frequency->isRecurring()
                ? now()->addMonths(2)->toDateString()
                : null,
            'installments_count' => null,
        ], $overrides));
    }

    private function mailbox(
        Domain $domain,
        EmailProvider $provider,
        string $address,
        EmailAccountOrigin $origin,
        ?string $password = null,
        EmailAccountStatus $status = EmailAccountStatus::Activa,
    ): EmailAccount {
        return EmailAccount::factory()->for($domain)->create([
            'email_provider_id' => $provider->id,
            'email_address' => $address,
            'password' => $password,
            'origin' => $origin,
            'status' => $status,
            'provisioned_at' => $origin === EmailAccountOrigin::Provisioned ? now()->subMonths(6) : null,
        ]);
    }
}
