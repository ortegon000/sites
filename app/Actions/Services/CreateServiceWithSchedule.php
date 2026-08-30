<?php

namespace App\Actions\Services;

use App\Actions\Charges\GenerateScheduledCharges;
use App\Enums\ServiceBillingFrequency;
use App\Models\Project;
use App\Models\Service;

class CreateServiceWithSchedule
{
    public function __construct(private GenerateScheduledCharges $generateScheduledCharges) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Project $project, array $attributes): Service
    {
        $service = $project->services()->create($attributes);

        if ($service->billing_frequency === ServiceBillingFrequency::Installment) {
            $this->createInstallments($service);
        } elseif (in_array($service->billing_frequency, [ServiceBillingFrequency::Monthly, ServiceBillingFrequency::Annual], true)) {
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
