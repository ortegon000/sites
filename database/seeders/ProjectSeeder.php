<?php

namespace Database\Seeders;

use App\Enums\ClientType;
use App\Enums\UserRole;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Seed a few projects with services and charges in varied states, so the
     * UI has data to show without needing to run `charges:process` first.
     */
    public function run(): void
    {
        $staff = User::where('role', UserRole::Staff)->first();
        $collaborator = User::where('role', UserRole::Collaborator)->first();

        Client::where('type', ClientType::Client)->take(3)->get()->each(function (Client $client) use ($staff, $collaborator) {
            $project = Project::factory()->for($client)->create();

            $project->users()->attach(array_filter([$staff?->id, $collaborator?->id]));

            $monthly = Service::factory()->monthly()->for($project)->create();
            Charge::factory()->for($monthly)->pending()->create();
            Charge::factory()->for($monthly)->paid()->create();
            Charge::factory()->for($monthly)->overdue()->create();

            $oneTime = Service::factory()->oneTime()->for($project)->create();
            Charge::factory()->for($oneTime)->paid()->create();

            $installment = Service::factory()->installment(3)->for($project)->create();
            Charge::factory()->for($installment)->pending()->create();
        });
    }
}
