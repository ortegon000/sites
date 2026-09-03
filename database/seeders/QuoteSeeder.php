<?php

namespace Database\Seeders;

use App\Actions\Quotes\AcceptQuote;
use App\Actions\Quotes\RejectQuote;
use App\Enums\ClientType;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cotizaciones en sus cuatro momentos: una recién enviada a un prospecto, una
 * vencida sin respuesta, una aceptada que ya generó su línea cobrable y una
 * rechazada con su razón. Es el ciclo completo que antes vivía como filas
 * "Pendiente" sin costo, con el precio escrito en las notas.
 */
class QuoteSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::where('role', UserRole::Staff)->firstOrFail();
        $prospect = Client::where('type', ClientType::Prospect)->orderBy('id')->firstOrFail();
        $demo = Client::where('name', 'Cliente Demo')->firstOrFail();
        $tacos = Client::where('name', 'Tacos El Güero')->firstOrFail();

        /** Marcada como proyecto: si el prospecto acepta, el sitio nace como trabajo con su propio expediente. */
        Quote::factory()->for($prospect)->sent()->asProject()->create([
            'name' => 'Sitio web institucional',
            'description' => 'Sitio de cinco secciones con blog y formulario.',
            'category' => ServiceCategory::Website,
            'billing_frequency' => ServiceBillingFrequency::OneTime,
            'amount' => '38000.00',
            'currency' => $prospect->currency,
            'valid_until' => now()->addDays(20)->toDateString(),
            'notes' => 'Pidió que le incluyéramos la migración de su blog viejo.',
        ]);

        /** Vencida sin respuesta: `charges:process` la expira en la siguiente corrida. */
        Quote::factory()->for($demo)->expiring()->create([
            'name' => 'Rediseño de la tienda en línea',
            'category' => ServiceCategory::Website,
            'billing_frequency' => ServiceBillingFrequency::OneTime,
            'amount' => '55000.00',
            'currency' => $demo->currency,
        ]);

        $accepted = Quote::factory()->for($tacos)->sent()->create([
            'name' => 'Mejora continua del sitio',
            'category' => ServiceCategory::Maintenance,
            'billing_frequency' => ServiceBillingFrequency::Monthly,
            'amount' => '5500.00',
            'currency' => $tacos->currency,
        ]);

        app(AcceptQuote::class)->handle($accepted, $staff);

        $rejected = Quote::factory()->for($demo)->sent()->create([
            'name' => 'Campaña de lanzamiento',
            'category' => ServiceCategory::AdsManagement,
            'billing_frequency' => ServiceBillingFrequency::Monthly,
            'amount' => '9500.00',
            'currency' => $demo->currency,
        ]);

        app(RejectQuote::class)->handle($rejected, 'Lo pospuso para el siguiente trimestre.');
    }
}
