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
     * Hosting, SSL, dominio y correo cuelgan del cliente y no del proyecto:
     * son costos del dominio que siguen existiendo aunque el proyecto se
     * cierre, así que la plantilla los crea sueltos aunque haya nacido de un
     * proyecto Web.
     *
     * @param  array<int, array{name: string, category: string, billing_frequency: string, amount: string}>  $services
     */
    public function handle(Project $project, array $services): void
    {
        $startsOn = $project->started_at?->toDateString() ?? today()->toDateString();

        foreach ($services as $service) {
            $category = ServiceCategory::from($service['category']);

            $this->createServiceWithSchedule->handle($project->client, [
                'name' => $service['name'],
                'description' => null,
                'category' => $category,
                'billing_frequency' => ServiceBillingFrequency::from($service['billing_frequency']),
                'amount' => $service['amount'],
                'currency' => $project->client->currency,
                'status' => ServiceStatus::Activo,
                'starts_on' => $startsOn,
                'installments_count' => null,
            ], $category->belongsToDomain() ? null : $project);
        }
    }
}
