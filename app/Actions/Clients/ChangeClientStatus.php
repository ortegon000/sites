<?php

namespace App\Actions\Clients;

use App\Enums\ClientNoteType;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\User;

class ChangeClientStatus
{
    public function handle(Client $client, ClientStatus $status, User $actor): Client
    {
        $previous = $client->status;

        $client->status = $status;

        if ($status === ClientStatus::Ganado && $client->type === ClientType::Prospect) {
            $client->type = ClientType::Client;
            $client->won_at = now();
        }

        $client->save();

        $client->notes()->create([
            'user_id' => $actor->id,
            'type' => ClientNoteType::StatusChange,
            'body' => "Estatus cambiado de \"{$previous->label()}\" a \"{$status->label()}\".",
        ]);

        return $client;
    }
}
