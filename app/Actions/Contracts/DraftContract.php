<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Enums\ServiceBillingFrequency;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\View;

class DraftContract
{
    /**
     * Arma el borrador con lo que ya está en el sistema: los servicios que
     * ampara, sus montos, sus subtareas como entregables y la vigencia. El
     * texto se renderiza una sola vez y desde ahí se edita en el contrato, no
     * en la plantilla: lo que se firmó no debe cambiar cuando cambie el
     * catálogo.
     *
     * @param  Collection<int, Service>  $services
     */
    public function handle(
        Client $client,
        Collection $services,
        string $title,
        string $startsOn,
        ?string $endsOn = null,
        ?Project $project = null,
        ?Quote $quote = null,
    ): Contract {
        $number = Contract::nextNumber();

        $body = View::make('contracts.default', [
            'number' => $number,
            'client' => $client,
            'contact' => $client->primaryContact(),
            'services' => $services,
            'items' => $services->flatMap->items,
            'startsOn' => CarbonImmutable::parse($startsOn),
            'endsOn' => $endsOn ? CarbonImmutable::parse($endsOn) : null,
            'currency' => $client->currency,
            'recurringTotal' => $services
                ->filter(fn (Service $service) => $service->billing_frequency->isRecurring())
                ->sum(fn (Service $service) => (float) $service->amount),
            'oneTimeTotal' => $services
                ->filter(fn (Service $service) => $service->billing_frequency === ServiceBillingFrequency::OneTime)
                ->sum(fn (Service $service) => (float) $service->amount),
        ])->render();

        $contract = $client->contracts()->create([
            'project_id' => $project?->id,
            'quote_id' => $quote?->id,
            'number' => $number,
            'title' => $title,
            'status' => ContractStatus::Borrador,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'currency' => $client->currency,
            /** Las ramas de la plantilla dejan huecos según qué haya que
             *  imprimir; normalizarlos aquí evita perseguirlos uno por uno. */
            'body' => trim((string) preg_replace("/\n{3,}/", "\n\n", $body)),
        ]);

        $contract->services()->sync($services->pluck('id'));

        return $contract;
    }
}
