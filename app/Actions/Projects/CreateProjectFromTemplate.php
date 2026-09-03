<?php

namespace App\Actions\Projects;

use App\Actions\Services\CreateServiceWithSchedule;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Models\Project;

class CreateProjectFromTemplate
{
    public function __construct(private CreateServiceWithSchedule $createServiceWithSchedule) {}

    /**
     * Create the services a project type suggests. It delegates to
     * CreateServiceWithSchedule so installments, `next_charge_date` and the
     * first charge behave exactly as they do for a hand-added service.
     *
     * @param  array<int, array{name: string, category: string, billing_frequency: string, amount: string}>  $services
     */
    public function handle(Project $project, array $services): void
    {
        $startsOn = $project->started_at?->toDateString() ?? today()->toDateString();

        foreach ($services as $service) {
            $this->createServiceWithSchedule->handle($project->client, [
                'name' => $service['name'],
                'description' => null,
                'category' => ServiceCategory::from($service['category']),
                'billing_frequency' => ServiceBillingFrequency::from($service['billing_frequency']),
                'amount' => $service['amount'],
                'currency' => $project->client->currency,
                'status' => ServiceStatus::Activo,
                'starts_on' => $startsOn,
                'installments_count' => null,
            ], $project);
        }
    }
}
