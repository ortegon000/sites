<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Crea la cuenta de administrador real de la agencia, aparte de los
     * usuarios de demostración de UserSeeder. No está registrado en
     * DatabaseSeeder a propósito: se corre a mano con
     * `php artisan db:seed --class=AdminSeeder` después de un `migrate:fresh`.
     *
     * La contraseña se lee del entorno (`ADMIN_SEED_PASSWORD`) porque este
     * repositorio es remoto: dejarla en el código la publicaría.
     */
    public function run(): void
    {
        $admin = config('seeding.admin');

        if (blank($admin['password'])) {
            $this->command->warn('AdminSeeder omitido: define ADMIN_SEED_PASSWORD en tu .env.');

            return;
        }

        if (User::where('email', $admin['email'])->exists()) {
            return;
        }

        User::create([
            'name' => $admin['name'],
            'email' => $admin['email'],
            'email_verified_at' => now(),
            'password' => Hash::make($admin['password']),
            'role' => UserRole::Admin,
            'remember_token' => Str::random(10),
        ]);
    }
}
