<?php

use App\Actions\Charges\GenerateScheduledCharges;
use App\Actions\Services\CreateServiceWithSchedule;
use App\Enums\ChargeStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Project;

function createProjectForScheduling(): Project
{
    return Project::factory()->for(Client::factory()->client())->create();
}

test('creating an installment service generates equal monthly installments and a charge for the first one', function () {
    $project = createProjectForScheduling();

    $service = app(CreateServiceWithSchedule::class)->handle($project, [
        'name' => 'Rediseño en 3 pagos',
        'description' => null,
        'billing_frequency' => ServiceBillingFrequency::Installment,
        'amount' => '1000.00',
        'currency' => 'MXN',
        'status' => ServiceStatus::Activo,
        'starts_on' => now()->toDateString(),
        'installments_count' => 3,
    ]);

    expect($service->installments)->toHaveCount(3)
        ->and($service->installments->pluck('amount')->unique())->toHaveCount(1)
        ->and($service->charges)->toHaveCount(1)
        ->and($service->charges->first()->status)->toBe(ChargeStatus::Pendiente)
        ->and($service->charges->first()->service_installment_id)->toBe($service->installments->first()->id);
});

test('creating a monthly service sets next_charge_date and generates an immediate charge when starting today', function () {
    $project = createProjectForScheduling();

    $service = app(CreateServiceWithSchedule::class)->handle($project, [
        'name' => 'Mantenimiento',
        'description' => null,
        'billing_frequency' => ServiceBillingFrequency::Monthly,
        'amount' => '800.00',
        'currency' => 'MXN',
        'status' => ServiceStatus::Activo,
        'starts_on' => now()->toDateString(),
        'installments_count' => null,
    ]);

    $service->refresh();

    expect($service->charges)->toHaveCount(1)
        ->and($service->next_charge_date->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
});

test('creating a one_time service generates a single charge and never recurs', function () {
    $project = createProjectForScheduling();

    $service = app(CreateServiceWithSchedule::class)->handle($project, [
        'name' => 'Landing page',
        'description' => null,
        'billing_frequency' => ServiceBillingFrequency::OneTime,
        'amount' => '3000.00',
        'currency' => 'MXN',
        'status' => ServiceStatus::Activo,
        'starts_on' => now()->toDateString(),
        'installments_count' => null,
    ]);

    expect($service->charges)->toHaveCount(1)
        ->and($service->next_charge_date)->toBeNull();

    app(GenerateScheduledCharges::class)->handle($service);

    expect($service->charges()->count())->toBe(1);
});
