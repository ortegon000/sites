<?php

namespace Database\Seeders;

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

        $projects = Project::take(2)->get();

        $projects->first()?->agencies()->attach($weInvoiceThem, [
            'billing_direction' => AgencyBillingDirection::WeInvoiceThem,
            'notes' => 'Subcontratamos diseño para este proyecto.',
        ]);

        $projects->last()?->agencies()->attach($theyInvoiceUs, [
            'billing_direction' => AgencyBillingDirection::TheyInvoiceUs,
            'notes' => 'Ellos nos subcontrataron para este proyecto.',
        ]);
    }
}
