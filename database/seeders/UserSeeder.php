<?php

namespace Database\Seeders;

use App\Models\Contact;
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

        /**
         * El acceso de portal cuelga de la persona, así que este usuario ve las
         * tres empresas de Juan Pérez con un solo login.
         */
        $owner = Contact::where('email', 'juan.perez@ejemplo.test')->firstOrFail();

        User::factory()->client($owner)->create([
            'name' => 'Juan Pérez',
            'email' => 'cliente@example.com',
        ]);
    }
}
