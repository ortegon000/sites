<?php

namespace App\Actions\Clients;

use App\Enums\ClientNoteType;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\User;

class ChangeClientStatus
{
    /**
     * $actor es null cuando el cambio lo dispara el propio cliente -al
     * aceptar su cotización desde el enlace público, por ejemplo- y no
     * alguien del equipo: la nota queda sin autor en vez de inventarle uno.
     */
    public function handle(Client $client, ClientStatus $status, ?User $actor = null): Client
    {
        $previous = $client->status;

        $client->status = $status;

        if ($status === ClientStatus::Ganado && $client->type === ClientType::Prospect) {
            $client->type = ClientType::Client;
            $client->won_at = now();
        }

        $client->save();

        $client->notes()->create([
            'user_id' => $actor?->id,
            'type' => ClientNoteType::StatusChange,
            'body' => "Estatus cambiado de \"{$previous->label()}\" a \"{$status->label()}\".",
        ]);

        return $client;
    }
}
