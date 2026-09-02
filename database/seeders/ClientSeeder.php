<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Empresas y prospectos con sus contactos. Los nombres son fijos y no de
     * faker porque `ProjectSeeder` construye escenarios concretos sobre ellos,
     * y porque un demo se lee mejor con nombres que parecen clientes reales.
     *
     * Incluye a propósito el caso que motivó separar `contacts` de `clients`:
     * una misma persona dueña de tres empresas, escrita una sola vez.
     */
    public function run(): void
    {
        $owner = Contact::factory()->create([
            'name' => 'Juan Pérez',
            'email' => 'juan.perez@ejemplo.test',
            'phone' => '55 1234 5678',
            'notes' => 'Dueño de varias empresas; todo se trata directamente con él.',
        ]);

        $demo = Client::factory()->client()->create([
            'name' => 'Cliente Demo',
            'company_name' => 'Cliente Demo S.A. de C.V.',
            'source' => 'referido',
        ]);
        $demo->contacts()->attach($owner, ['is_primary' => true, 'role' => 'Director general']);

        $tacos = Client::factory()->client()->create([
            'name' => 'Tacos El Güero',
            'company_name' => 'Alimentos El Güero S.A. de C.V.',
            'source' => 'referido',
        ]);
        $tacos->contacts()->attach($owner, ['is_primary' => true, 'role' => 'Propietario']);

        $inmobiliaria = Client::factory()->client()->create([
            'name' => 'Inmobiliaria Norte',
            'company_name' => 'Inmobiliaria Norte S. de R.L.',
            'source' => 'referido',
        ]);
        $inmobiliaria->contacts()->attach($owner, ['is_primary' => true, 'role' => 'Propietario']);

        /** Factura en dólares: ejercita el soporte multi-moneda. */
        Client::factory()->client()->withContact()->create([
            'name' => 'Estudio Marea',
            'company_name' => 'Estudio Marea LLC',
            'currency' => 'USD',
            'source' => 'google',
        ]);

        /** Llega a través de una agencia: `ProjectSeeder` demuestra la herencia. */
        Client::factory()->client()->withContact()->create([
            'name' => 'Clínica Sur',
            'company_name' => 'Servicios Médicos del Sur S.C.',
            'source' => 'agencia',
        ]);

        Client::factory()->prospect()->withContact()->count(6)->create();
    }
}
