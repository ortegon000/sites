<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Siembra empresas y prospectos con sus contactos. Incluye a propósito el
     * caso que motivó separar `contacts` de `clients`: una misma persona dueña
     * de tres empresas, escrita una sola vez.
     */
    public function run(): void
    {
        $demoClient = Client::factory()->client()->create([
            'name' => 'Cliente Demo',
            'company_name' => 'Cliente Demo S.A. de C.V.',
        ]);

        $owner = Contact::factory()->create([
            'name' => 'Juan Pérez',
            'email' => 'juan.perez@ejemplo.test',
            'phone' => '55 1234 5678',
            'notes' => 'Dueño de varias empresas; todo se trata directamente con él.',
        ]);

        $demoClient->contacts()->attach($owner, ['is_primary' => true, 'role' => 'Director general']);

        Client::factory()
            ->client()
            ->count(2)
            ->create()
            ->each(fn (Client $client) => $client->contacts()->attach($owner, [
                'is_primary' => true,
                'role' => 'Propietario',
            ]));

        Client::factory()->client()->withContact()->count(2)->create();

        Client::factory()->prospect()->withContact()->count(6)->create();
    }
}
