<?php

namespace App\Actions\Quotes;

use App\Actions\Clients\ChangeClientStatus;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\User;

class SendQuote
{
    public function __construct(private ChangeClientStatus $changeClientStatus) {}

    /**
     * Marca la cotización como enviada y, si el destinatario todavía es un
     * prospecto, mueve su estatus a "propuesta enviada": el pipeline y las
     * cotizaciones cuentan la misma historia, y tenerlos que actualizar por
     * separado es cómo se desincronizan.
     */
    public function handle(Quote $quote, User $actor): Quote
    {
        $quote->update([
            'status' => QuoteStatus::Enviada,
            'sent_at' => now(),
        ]);

        $client = $quote->client;

        if ($client->type === ClientType::Prospect && $client->status !== ClientStatus::PropuestaEnviada) {
            $this->changeClientStatus->handle($client, ClientStatus::PropuestaEnviada, $actor);
        }

        return $quote;
    }
}
