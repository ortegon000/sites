<?php

namespace App\Actions\Quotes;

use App\Actions\Clients\ChangeClientStatus;
use App\Actions\Services\CreateServiceWithSchedule;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\QuoteStatus;
use App\Enums\ServiceStatus;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use Illuminate\Support\Collection;

class AcceptQuote
{
    public function __construct(
        private CreateServiceWithSchedule $createServiceWithSchedule,
        private ChangeClientStatus $changeClientStatus,
    ) {}

    /**
     * El cliente aceptó: cada renglón nace como su propia línea cobrable y la
     * cotización queda como constancia de qué se ofreció y cuándo se decidió.
     *
     * Si quien aceptó era un prospecto, se gana: una cotización aceptada es
     * exactamente el momento en que deja de serlo, y hacerlo aquí evita el
     * paso manual que se olvida.
     *
     * Lo cotizado abre proyecto solo si se marcó así al capturarla y tiene al
     * menos un renglón que no sea de dominio (hosting/SSL/dominio/correo
     * cuelgan siempre del cliente, así que una cotización puramente de
     * dominio nunca abre proyecto aunque el interruptor haya quedado
     * prendido).
     */
    public function handle(Quote $quote, User $actor): Quote
    {
        $lineItems = $quote->lineItems;

        $projectEligible = $lineItems->filter(fn (QuoteLineItem $item) => ! $item->category->belongsToDomain());

        $project = $quote->project ?? ($quote->is_project && $projectEligible->isNotEmpty()
            ? $this->openProject($quote, $projectEligible)
            : null);

        foreach ($lineItems as $item) {
            $service = $this->createServiceWithSchedule->handle($quote->client, [
                'name' => $item->name,
                'description' => $item->description,
                'category' => $item->category,
                'billing_frequency' => $item->billing_frequency,
                'amount' => $item->amount,
                'currency' => $quote->currency,
                'status' => ServiceStatus::Activo,
                'starts_on' => today()->toDateString(),
                'installments_count' => null,
            ], $item->category->belongsToDomain() ? null : $project);

            $item->update(['service_id' => $service->id]);
        }

        $quote->update([
            'status' => QuoteStatus::Aceptada,
            'decided_at' => now(),
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
     * porque aceptar es justamente el arranque del trabajo. El tipo lo marca
     * el primer renglón que sí describe trabajo de proyecto.
     *
     * @param  Collection<int, QuoteLineItem>  $projectEligible
     */
    private function openProject(Quote $quote, Collection $projectEligible): Project
    {
        return $quote->client->projects()->create([
            'name' => $quote->name,
            'description' => $quote->description,
            'type' => $projectEligible->first()?->category->projectType() ?? ProjectType::Other,
            'status' => ProjectStatus::Activo,
            'started_at' => today(),
        ]);
    }
}
