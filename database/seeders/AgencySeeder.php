<?php

namespace Database\Seeders;

use App\Actions\Clients\SyncClientAgencyToProjects;
use App\Enums\AgencyBillingDirection;
use App\Models\Agency;
use App\Models\Project;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    /**
     * Seed a few collaborator agencies and associate them with existing projects.
     */
    public function run(): void
    {
        $weInvoiceThem = Agency::factory()->create(['name' => 'Pixel Forge Studio']);
        $theyInvoiceUs = Agency::factory()->create(['name' => 'Northwind Digital']);
        Agency::factory()->create();

        $projects = Project::take(3)->get();

        $projects->get(0)?->agencies()->attach($weInvoiceThem, [
            'billing_direction' => AgencyBillingDirection::WeInvoiceThem,
            'notes' => 'Subcontratamos diseño para este proyecto.',
        ]);

        $projects->get(1)?->agencies()->attach($theyInvoiceUs, [
            'billing_direction' => AgencyBillingDirection::TheyInvoiceUs,
            'notes' => 'Ellos nos subcontrataron para este proyecto.',
        ]);

        // Demuestra la asignación cliente→agencia: el cliente del tercer
        // proyecto llega a través de esta agencia, así que su proyecto ya
        // existente queda vinculado automáticamente (sin dirección de
        // facturación aún, pendiente de que el staff la defina).
        if ($client = $projects->get(2)?->client) {
            $client->update(['agency_id' => $theyInvoiceUs->id]);
            app(SyncClientAgencyToProjects::class)->handle($client);
        }
    }
}
