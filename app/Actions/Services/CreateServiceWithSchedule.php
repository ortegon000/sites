<?php

namespace App\Actions\Services;

use App\Actions\Charges\GenerateScheduledCharges;
use App\Enums\ServiceBillingFrequency;
use App\Models\Client;
use App\Models\Project;
use App\Models\Service;

class CreateServiceWithSchedule
{
    public function __construct(private GenerateScheduledCharges $generateScheduledCharges) {}

    /**
     * Crea una línea cobrable del cliente y le arma su calendario. El proyecto
     * es opcional: agrupa las líneas de un trabajo grande, pero una renovación
     * anual de $4,000 no necesita uno.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Client $client, array $attributes, ?Project $project = null): Service
    {
        $service = $client->services()->create([
            ...$attributes,
            'project_id' => $project?->id,
        ]);

        if ($service->billing_frequency === ServiceBillingFrequency::Installment) {
            $this->createInstallments($service);
        } elseif ($service->billing_frequency->isRecurring()) {
            $service->update(['next_charge_date' => $service->starts_on]);
        }

        $this->generateScheduledCharges->handle($service);

        return $service;
    }

    private function createInstallments(Service $service): void
    {
        $count = $service->installments_count ?? 1;

        for ($number = 1; $number <= $count; $number++) {
            $service->installments()->create([
                'installment_number' => $number,
                'amount' => $service->amount,
                'due_date' => $service->starts_on->copy()->addMonthsNoOverflow($number - 1),
            ]);
        }
    }
}
