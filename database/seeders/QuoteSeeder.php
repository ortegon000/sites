<?php

namespace Database\Seeders;

use App\Actions\Quotes\AcceptQuote;
use App\Actions\Quotes\RejectQuote;
use App\Enums\ClientType;
use App\Enums\QuoteStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cotizaciones en todos sus momentos y formas: borrador, capturada dentro de
 * un proyecto que ya existe, de varios renglones esperando respuesta,
 * vencida, de puro dominio que no abre proyecto aunque se marque, aceptada
 * abriendo un proyecto nuevo de verdad, aceptada como línea suelta, y
 * rechazada con su razón.
 *
 * Está escrito como escenarios con nombre en vez de un bucle, igual que
 * `ProjectSeeder`: cada uno ejercita una combinación distinta de estatus,
 * renglones y destino, sobre los clientes y proyectos que los otros seeders
 * ya dejaron listos.
 */
class QuoteSeeder extends Seeder
{
    private User $staff;

    public function run(): void
    {
        $this->staff = User::where('role', UserRole::Staff)->firstOrFail();

        $this->seedDraftQuote();
        $this->seedProjectScopedQuote();
        $this->seedMultiLineProspectQuote();
        $this->seedDomainOnlyAcceptedQuote();
        $this->seedExpiredQuote();
        $this->seedAcceptedWithNewProjectQuote();
        $this->seedAcceptedStandaloneQuote();
        $this->seedRejectedQuote();
    }

    /**
     * Recién capturada, sin mandar todavía: nadie ha decidido nada y no
     * genera ningún cobro.
     */
    private function seedDraftQuote(): void
    {
        $client = Client::where('name', 'Inmobiliaria Norte')->firstOrFail();

        Quote::factory()->for($client)
            ->withLineItem([
                'name' => 'Landing de promociones',
                'description' => 'Una sola página para anunciar la promoción de fin de año.',
                'category' => ServiceCategory::Website,
                'billing_frequency' => ServiceBillingFrequency::OneTime,
                'amount' => '14000.00',
            ])
            ->create([
                'name' => 'Landing de promociones de fin de año',
                'description' => 'Página de aterrizaje con formulario de contacto y contador de la oferta.',
                'currency' => $client->currency,
                'valid_until' => now()->addDays(30)->toDateString(),
            ]);
    }

    /**
     * Capturada desde dentro de un proyecto que ya existe: `project_id` nace
     * puesto, así que "es un proyecto" nunca aplica, aceptarla no abre uno
     * nuevo y cuelga directo del que ya está.
     */
    private function seedProjectScopedQuote(): void
    {
        $client = Client::where('name', 'Cliente Demo')->firstOrFail();
        $project = Project::where('name', 'Mantenimiento Cliente Demo')->firstOrFail();

        Quote::factory()->for($client)->sent()
            ->withLineItem([
                'name' => 'Respaldos semanales adicionales',
                'description' => 'Respaldo completo cada semana en vez de cada mes.',
                'category' => ServiceCategory::Maintenance,
                'billing_frequency' => ServiceBillingFrequency::Monthly,
                'amount' => '900.00',
            ])
            ->create([
                'name' => 'Ampliar el mantenimiento con respaldos semanales',
                'project_id' => $project->id,
                'currency' => $client->currency,
                'valid_until' => now()->addDays(15)->toDateString(),
            ]);
    }

    /**
     * Marcada como proyecto: si el prospecto acepta, el sitio nace como
     * trabajo con su propio expediente. Mezcla un renglón de proyecto con dos
     * de dominio, que cuelgan siempre del cliente aunque el resto abra
     * proyecto.
     */
    private function seedMultiLineProspectQuote(): void
    {
        $prospect = Client::where('type', ClientType::Prospect)->orderBy('id')->firstOrFail();

        Quote::factory()->for($prospect)->sent()->asProject()
            ->withLineItem([
                'name' => 'Diseño y desarrollo del sitio',
                'description' => 'Cinco secciones, blog y formulario de contacto.',
                'category' => ServiceCategory::Website,
                'billing_frequency' => ServiceBillingFrequency::OneTime,
                'amount' => '38000.00',
            ])
            ->withLineItem([
                'name' => 'Hosting anual',
                'category' => ServiceCategory::Hosting,
                'billing_frequency' => ServiceBillingFrequency::Annual,
                'amount' => '1800.00',
            ])
            ->withLineItem([
                'name' => 'Certificado SSL',
                'category' => ServiceCategory::Ssl,
                'billing_frequency' => ServiceBillingFrequency::Annual,
                'amount' => '600.00',
            ])
            ->create([
                'name' => 'Sitio web institucional',
                'description' => 'Sitio de cinco secciones con blog y formulario.',
                'currency' => $prospect->currency,
                'valid_until' => now()->addDays(20)->toDateString(),
                'notes' => 'Pidió que le incluyéramos la migración de su blog viejo.',
            ]);
    }

    /**
     * Puro dominio: aunque se marque como proyecto al aceptarse, ningún
     * renglón lo abre porque hosting y dominio siempre cuelgan del cliente.
     * Se acepta aquí mismo para dejar esa constancia en las líneas cobrables.
     */
    private function seedDomainOnlyAcceptedQuote(): void
    {
        $client = Client::where('name', 'Clínica Sur')->firstOrFail();

        $quote = Quote::factory()->for($client)->sent()->asProject()
            ->withLineItem([
                'name' => 'Hosting anual',
                'category' => ServiceCategory::Hosting,
                'billing_frequency' => ServiceBillingFrequency::Annual,
                'amount' => '2200.00',
            ])
            ->withLineItem([
                'name' => 'Renovación de dominio',
                'category' => ServiceCategory::Domain,
                'billing_frequency' => ServiceBillingFrequency::Annual,
                'amount' => '450.00',
            ])
            ->create([
                'name' => 'Renovación de hosting y dominio',
                'currency' => $client->currency,
            ]);

        app(AcceptQuote::class)->handle($quote, $this->staff);
    }

    /**
     * Vencida de a de veras, no a punto de vencer: para verla así sin tener
     * que correr `charges:process` primero. En dólares, para no dejar ese
     * caso solo en servicios.
     */
    private function seedExpiredQuote(): void
    {
        $client = Client::where('name', 'Estudio Marea')->firstOrFail();

        Quote::factory()->for($client)
            ->withLineItem([
                'name' => 'Actualización de la plataforma',
                'category' => ServiceCategory::Website,
                'billing_frequency' => ServiceBillingFrequency::OneTime,
                'amount' => '2400.00',
            ])
            ->create([
                'name' => 'Actualización de la plataforma',
                'currency' => 'USD',
                'status' => QuoteStatus::Expirada,
                'sent_at' => now()->subDays(60),
                'valid_until' => now()->subDays(30)->toDateString(),
            ]);
    }

    /**
     * Aceptada marcándola como proyecto: a diferencia de las demás, esta sí
     * tiene al menos un renglón que no es de dominio, así que de verdad abre
     * un proyecto nuevo -no uno que ya existiera de antes- y gana al
     * prospecto.
     */
    private function seedAcceptedWithNewProjectQuote(): void
    {
        $prospect = Client::where('type', ClientType::Prospect)->orderBy('id')->skip(1)->firstOrFail();

        $quote = Quote::factory()->for($prospect)->sent()->asProject()
            ->withLineItem([
                'name' => 'Tienda en línea',
                'description' => 'Catálogo, carrito y pagos con tarjeta.',
                'category' => ServiceCategory::Website,
                'billing_frequency' => ServiceBillingFrequency::OneTime,
                'amount' => '62000.00',
            ])
            ->withLineItem([
                'name' => 'Hosting anual',
                'category' => ServiceCategory::Hosting,
                'billing_frequency' => ServiceBillingFrequency::Annual,
                'amount' => '2400.00',
            ])
            ->create([
                'name' => 'Tienda en línea',
                'description' => 'Catálogo de hasta 200 productos con pagos con tarjeta.',
                'currency' => $prospect->currency,
            ]);

        app(AcceptQuote::class)->handle($quote, $this->staff);
    }

    /**
     * Aceptada sin marcarla como proyecto: la línea cobrable nace suelta,
     * como la mayoría del trabajo cotizado.
     */
    private function seedAcceptedStandaloneQuote(): void
    {
        $client = Client::where('name', 'Tacos El Güero')->firstOrFail();

        $quote = Quote::factory()->for($client)->sent()
            ->withLineItem([
                'name' => 'Mejora continua del sitio',
                'category' => ServiceCategory::Maintenance,
                'billing_frequency' => ServiceBillingFrequency::Monthly,
                'amount' => '5500.00',
            ])
            ->create([
                'name' => 'Mejora continua del sitio',
                'currency' => $client->currency,
            ]);

        app(AcceptQuote::class)->handle($quote, $this->staff);
    }

    /**
     * Rechazada, con la razón que dio el cliente.
     */
    private function seedRejectedQuote(): void
    {
        $client = Client::where('name', 'Cliente Demo')->firstOrFail();

        $quote = Quote::factory()->for($client)->sent()
            ->withLineItem([
                'name' => 'Campaña de lanzamiento',
                'category' => ServiceCategory::AdsManagement,
                'billing_frequency' => ServiceBillingFrequency::Monthly,
                'amount' => '9500.00',
            ])
            ->create([
                'name' => 'Campaña de lanzamiento',
                'currency' => $client->currency,
            ]);

        app(RejectQuote::class)->handle($quote, 'Lo pospuso para el siguiente trimestre.');
    }
}
