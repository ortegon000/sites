<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ClientSeeder::class,
            UserSeeder::class,
            ClientNoteSeeder::class,
            AgencySeeder::class,
            EmailProviderSeeder::class,
            ProjectSeeder::class,
            RenewalSeeder::class,
            QuoteSeeder::class,
        ]);
    }
}
