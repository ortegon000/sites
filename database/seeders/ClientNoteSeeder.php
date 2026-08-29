<?php

namespace Database\Seeders;

use App\Enums\ClientNoteType;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClientNoteSeeder extends Seeder
{
    /**
     * Seed a few activity notes per client, authored by internal users.
     */
    public function run(): void
    {
        $authors = User::whereIn('role', [UserRole::Admin, UserRole::Staff])->get();

        Client::all()->each(function (Client $client) use ($authors) {
            ClientNote::factory()
                ->count(random_int(1, 3))
                ->for($client)
                ->state(fn () => [
                    'type' => fake()->randomElement(ClientNoteType::cases()),
                    'user_id' => $authors->random()->id,
                ])
                ->create();
        });
    }
}
