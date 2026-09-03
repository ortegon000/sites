<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Quote;

class RejectQuote
{
    /**
     * El cliente dijo que no. No se toca su estatus de prospecto: un "no" a
     * esta cotización no lo pierde como prospecto, y darlo por perdido es una
     * decisión de quien lo atiende, no un efecto secundario.
     */
    public function handle(Quote $quote, ?string $reason = null): Quote
    {
        $quote->update([
            'status' => QuoteStatus::Rechazada,
            'decided_at' => now(),
            'notes' => $reason ?: $quote->notes,
        ]);

        return $quote;
    }
}
