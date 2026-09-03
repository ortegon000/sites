<?php

namespace App\Actions\Clients;

use App\Models\Client;

class SyncClientAgencyToProjects
{
    /**
     * Attach the client's assigned agency to each of its projects that isn't
     * already linked to it. Who gets invoiced is a property of the agency
     * itself, so the association only carries notes; existing ones are never
     * touched, so a client's agency change never overwrites what staff wrote
     * on its projects.
     */
    public function handle(Client $client): void
    {
        if (! $client->agency_id) {
            return;
        }

        foreach ($client->projects as $project) {
            if ($project->agencies()->where('agency_id', $client->agency_id)->exists()) {
                continue;
            }

            $project->agencies()->attach($client->agency_id, ['notes' => null]);
        }
    }
}
