<?php

namespace App\Actions\Charges;

use App\Enums\ChargeStatus;
use App\Enums\ServiceBillingFrequency;
use App\Models\Service;
use App\Models\ServiceInstallment;

class GenerateScheduledCharges
{
    public function handle(?Service $only = null): void
    {
        $this->generateOneTimeCharges($only);
        $this->generateRecurringCharges($only);
        $this->generateInstallmentCharges($only);
    }

    private function generateOneTimeCharges(?Service $only): void
    {
        Service::query()
            ->where('billing_frequency', ServiceBillingFrequency::OneTime)
            ->where('starts_on', '<=', today())
            ->whereDoesntHave('charges')
            ->when($only, fn ($query) => $query->whereKey($only->id))
            ->each(function (Service $service): void {
                $service->charges()->create([
                    'amount' => $service->amount,
                    'currency' => $service->currency,
                    'status' => ChargeStatus::Pendiente,
                    'due_date' => $service->starts_on,
                ]);
            });
    }

    private function generateRecurringCharges(?Service $only): void
    {
        Service::query()
            ->whereIn('billing_frequency', [ServiceBillingFrequency::Monthly, ServiceBillingFrequency::Annual])
            ->whereNotNull('next_charge_date')
            ->where('next_charge_date', '<=', today())
            ->when($only, fn ($query) => $query->whereKey($only->id))
            ->each(function (Service $service): void {
                while ($service->next_charge_date !== null && $service->next_charge_date->lte(today())) {
                    $dueDate = $service->next_charge_date;

                    $service->charges()->create([
                        'amount' => $service->amount,
                        'currency' => $service->currency,
                        'status' => ChargeStatus::Pendiente,
                        'due_date' => $dueDate,
                    ]);

                    $service->next_charge_date = $service->billing_frequency === ServiceBillingFrequency::Monthly
                        ? $dueDate->copy()->addMonthNoOverflow()
                        : $dueDate->copy()->addYear();

                    $service->save();
                }
            });
    }

    private function generateInstallmentCharges(?Service $only): void
    {
        ServiceInstallment::query()
            ->whereDoesntHave('charge')
            ->where('due_date', '<=', today())
            ->when($only, fn ($query) => $query->where('service_id', $only->id))
            ->with('service')
            ->each(function (ServiceInstallment $installment): void {
                $installment->service->charges()->create([
                    'service_installment_id' => $installment->id,
                    'amount' => $installment->amount,
                    'currency' => $installment->service->currency,
                    'status' => ChargeStatus::Pendiente,
                    'due_date' => $installment->due_date,
                ]);
            });
    }
}
