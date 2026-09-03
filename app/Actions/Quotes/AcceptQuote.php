<?php

namespace App\Actions\Quotes;

use App\Actions\Clients\ChangeClientStatus;
use App\Actions\Services\CreateServiceWithSchedule;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Enums\ServiceStatus;
use App\Models\Project;
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
     *
     * Lo cotizado abre proyecto solo si se marcó así al capturarlo. Sin esa
     * marca la línea nace suelta del cliente: la mayoría del trabajo cotizado
     * —una renovación, una mejora al sitio— no necesita proyecto.
     */
    public function handle(Quote $quote, User $actor): Quote
    {
        $project = $quote->project ?? ($quote->is_project ? $this->openProject($quote) : null);

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
        ], $project);

        $quote->update([
            'status' => QuoteStatus::Aceptada,
            'decided_at' => now(),
            'service_id' => $service->id,
            'project_id' => $project?->id,
        ]);

        $client = $quote->client;

        if ($client->type === ClientType::Prospect) {
            $this->changeClientStatus->handle($client, ClientStatus::Ganado, $actor);
        }

        return $quote;
    }

    /**
     * El proyecto nace con lo que la cotización ya sabe: se abre hoy y activo,
     * porque aceptar es justamente el arranque del trabajo.
     */
    private function openProject(Quote $quote): Project
    {
        return $quote->client->projects()->create([
            'name' => $quote->name,
            'description' => $quote->description,
            'type' => $quote->category->projectType(),
            'status' => ProjectStatus::Activo,
            'started_at' => today(),
        ]);
    }
}
