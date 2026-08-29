<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's users, one per role.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->staff()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
        ]);

        User::factory()->collaborator()->create([
            'name' => 'Colaborador Externo',
            'email' => 'colaborador@example.com',
        ]);

        $demoClient = Client::where('name', 'Cliente Demo')->firstOrFail();

        User::factory()->client($demoClient)->create([
            'name' => 'Usuario de Cliente Demo',
            'email' => 'cliente@example.com',
        ]);
    }
}
