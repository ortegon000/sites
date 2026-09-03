<?php

namespace App\Actions\Quotes;

use App\Actions\Clients\ChangeClientStatus;
use App\Actions\Services\CreateServiceWithSchedule;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\QuoteStatus;
use App\Enums\ServiceStatus;
use App\Models\Quote;
use App\Models\User;

class AcceptQuote
{
    public function __construct(
        private CreateServiceWithSchedule $createServiceWithSchedule,
        private ChangeClientStatus $changeClientStatus,
    ) {}

    /**
     * El cliente aceptó: nace la línea cobrable con lo cotizado y la cotización
     * queda como constancia de qué se ofreció y cuándo se decidió.
     *
     * Si quien aceptó era un prospecto, se gana: una cotización aceptada es
     * exactamente el momento en que deja de serlo, y hacerlo aquí evita el
     * paso manual que se olvida.
     */
    public function handle(Quote $quote, User $actor): Quote
    {
        $service = $this->createServiceWithSchedule->handle($quote->client, [
            'name' => $quote->name,
            'description' => $quote->description,
            'category' => $quote->category,
            'billing_frequency' => $quote->billing_frequency,
            'amount' => $quote->amount,
            'currency' => $quote->currency,
            'status' => ServiceStatus::Activo,
            'starts_on' => today()->toDateString(),
            'installments_count' => null,
        ], $quote->project);

        $quote->update([
            'status' => QuoteStatus::Aceptada,
            'decided_at' => now(),
            'service_id' => $service->id,
        ]);

        $client = $quote->client;

        if ($client->type === ClientType::Prospect) {
            $this->changeClientStatus->handle($client, ClientStatus::Ganado, $actor);
        }

        return $quote;
    }
}
