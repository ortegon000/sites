<?php

namespace App\Actions\Clients;

use App\Models\Client;

class SyncClientAgencyToProjects
{
    /**
     * Attach the client's assigned agency to each of its projects that
     * isn't already linked to it. The billing direction is left unset so
     * staff can define it from the project's own "Agencias colaboradoras"
     * card; existing associations (and their billing direction) are never
     * touched, so a client's agency change never overwrites prior billing
     * history on its projects.
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

            $project->agencies()->attach($client->agency_id, [
                'billing_direction' => null,
                'notes' => null,
            ]);
        }
    }
}
