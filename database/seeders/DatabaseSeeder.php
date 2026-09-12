<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Eduardo Ortega',
            'email' => 'ortegon000@gmail.com',
            'password' => Hash::make('l5M9f6WaSites@*@*'),
            'role' => UserRole::Admin,
        ]);

        $this->call([
            ClientSeeder::class,
            UserSeeder::class,
            ClientNoteSeeder::class,
            AgencySeeder::class,
            EmailProviderSeeder::class,
            ProjectSeeder::class,
            RenewalSeeder::class,
            QuoteSeeder::class,
            ContractSeeder::class,
        ]);
    }
}
