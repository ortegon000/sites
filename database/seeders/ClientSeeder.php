<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Seed the application's clients and prospects.
     */
    public function run(): void
    {
        Client::factory()->client()->create([
            'name' => 'Cliente Demo',
        ]);

        Client::factory()->client()->count(4)->create();

        Client::factory()->prospect()->count(6)->create();
    }
}
