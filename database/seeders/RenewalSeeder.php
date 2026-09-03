<?php

namespace Database\Seeders;

use App\Actions\Renewals\MarkRenewalRenewed;
use App\Actions\Renewals\OpenRenewalCycles;
use App\Enums\RenewalStatus;
use App\Models\Client;
use App\Models\License;
use App\Models\Renewal;
use Illuminate\Database\Seeder;

/**
 * Las licencias que caducan y el tablero de renovaciones ya poblado.
 *
 * Los ciclos se abren llamando a la misma acción que corre a diario en vez de
 * insertarlos a mano: si la regla de qué entra al tablero cambia, el seeder
 * cambia con ella y no miente sobre lo que se vería en la app.
 */
class RenewalSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Client::where('name', 'Cliente Demo')->firstOrFail();
        $clinic = Client::where('name', 'Clínica Sur')->firstOrFail();

        License::factory()->for($demo)->create([
            'name' => 'Brevo — plan Business',
            'vendor' => 'Brevo',
            'cost' => '2400.00',
            'currency' => $demo->currency,
            'renewal_date' => now()->addDays(18)->toDateString(),
            'auto_renew' => false,
        ]);

        License::factory()->for($demo)->create([
            'name' => 'Elementor Pro',
            'vendor' => 'Elementor',
            'cost' => '1800.00',
            'currency' => $demo->currency,
            'renewal_date' => now()->addMonths(7)->toDateString(),
            'auto_renew' => true,
        ]);

        License::factory()->for($clinic)->create([
            'name' => 'WhatsApp Business API',
            'vendor' => 'Meta',
            'cost' => '3200.00',
            'currency' => $clinic->currency,
            'renewal_date' => now()->addDays(45)->toDateString(),
            'auto_renew' => false,
        ]);

        app(OpenRenewalCycles::class)->handle();

        /** Uno ya avisado, esperando respuesta: el estado que antes no existía. */
        Renewal::query()
            ->whereIn('client_id', [$demo->id])
            ->where('status', RenewalStatus::PorAvisar)
            ->orderBy('due_date')
            ->first()
            ?->update([
                'status' => RenewalStatus::Avisado,
                'notified_at' => now()->subDays(3),
                'notes' => 'Se le avisó por correo; quedó de confirmar esta semana.',
            ]);

        /** Y uno ya cerrado, para que el historial no esté vacío. */
        $renewed = Renewal::query()
            ->where('client_id', $clinic->id)
            ->where('status', RenewalStatus::PorAvisar)
            ->orderByDesc('due_date')
            ->first();

        if ($renewed) {
            $renewed->update(['amount' => '3200.00', 'notified_at' => now()->subDays(10)]);

            app(MarkRenewalRenewed::class)->handle($renewed);
        }
    }
}
