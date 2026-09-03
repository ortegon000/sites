<?php

namespace Database\Seeders;

use App\Enums\AgencyBillingTarget;
use App\Models\Agency;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    /**
     * Solo las agencias. Asociarlas a proyectos es trabajo de `ProjectSeeder`,
     * que es quien conoce qué proyecto existe y para qué sirve cada uno.
     */
    public function run(): void
    {
        Agency::factory()->create([
            'name' => 'Pixel Forge Studio',
            'contact_name' => 'Ana Gómez',
            'email' => 'ana@pixelforge.test',
            /** Sus clientes los atendemos nosotros, pero la factura va a ellos. */
            'billing_target' => AgencyBillingTarget::Agency,
        ]);

        Agency::factory()->create([
            'name' => 'Northwind Digital',
            'contact_name' => 'Luis Ruiz',
            'email' => 'luis@northwind.test',
            /** Nos presentan al cliente y le facturamos directo a él. */
            'billing_target' => AgencyBillingTarget::Client,
        ]);

        Agency::factory()->create([
            'name' => 'Casa Bruma',
            'billing_target' => AgencyBillingTarget::Client,
        ]);
    }
}
