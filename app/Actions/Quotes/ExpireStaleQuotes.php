<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Quote;

class ExpireStaleQuotes
{
    /**
     * Una cotización enviada cuya vigencia ya pasó deja de estar esperando
     * respuesta: expira sola para que la lista de "pendientes de contestar" no
     * se llene de cosas de hace medio año. Volver a enviarla la reabre.
     */
    public function handle(): int
    {
        return Quote::query()
            ->where('status', QuoteStatus::Enviada)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', today())
            ->update(['status' => QuoteStatus::Expirada]);
    }
}
