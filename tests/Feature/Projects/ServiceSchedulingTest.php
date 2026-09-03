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

    $service = app(CreateServiceWithSchedule::class)->handle($project->client, [
        'name' => 'Rediseño en 3 pagos',
        'description' => null,
        'billing_frequency' => ServiceBillingFrequency::Installment,
        'amount' => '1000.00',
        'currency' => 'MXN',
        'status' => ServiceStatus::Activo,
        'starts_on' => now()->toDateString(),
        'installments_count' => 3,
    ], $project);

    expect($service->installments)->toHaveCount(3)
        ->and($service->installments->pluck('amount')->unique())->toHaveCount(1)
        ->and($service->charges)->toHaveCount(1)
        ->and($service->charges->first()->status)->toBe(ChargeStatus::Pendiente)
        ->and($service->charges->first()->service_installment_id)->toBe($service->installments->first()->id);
});

test('creating a monthly service sets next_charge_date and generates an immediate charge when starting today', function () {
    $project = createProjectForScheduling();

    $service = app(CreateServiceWithSchedule::class)->handle($project->client, [
        'name' => 'Mantenimiento',
        'description' => null,
        'billing_frequency' => ServiceBillingFrequency::Monthly,
        'amount' => '800.00',
        'currency' => 'MXN',
        'status' => ServiceStatus::Activo,
        'starts_on' => now()->toDateString(),
        'installments_count' => null,
    ], $project);

    $service->refresh();

    expect($service->charges)->toHaveCount(1)
        ->and($service->next_charge_date->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
});

test('creating a one_time service generates a single charge and never recurs', function () {
    $project = createProjectForScheduling();

    $service = app(CreateServiceWithSchedule::class)->handle($project->client, [
        'name' => 'Landing page',
        'description' => null,
        'billing_frequency' => ServiceBillingFrequency::OneTime,
        'amount' => '3000.00',
        'currency' => 'MXN',
        'status' => ServiceStatus::Activo,
        'starts_on' => now()->toDateString(),
        'installments_count' => null,
    ], $project);

    expect($service->charges)->toHaveCount(1)
        ->and($service->next_charge_date)->toBeNull();

    app(GenerateScheduledCharges::class)->handle($service);

    expect($service->charges()->count())->toBe(1);
});

test('creating a quarterly service advances next_charge_date by three months', function () {
    $project = createProjectForScheduling();

    $service = app(CreateServiceWithSchedule::class)->handle($project->client, [
        'name' => 'Mantenimiento trimestral',
        'description' => null,
        'billing_frequency' => ServiceBillingFrequency::Quarterly,
        'amount' => '2400.00',
        'currency' => 'MXN',
        'status' => ServiceStatus::Activo,
        'starts_on' => now()->toDateString(),
        'installments_count' => null,
    ], $project);

    $service->refresh();

    expect($service->charges)->toHaveCount(1)
        ->and($service->next_charge_date->toDateString())->toBe(now()->addMonthsNoOverflow(3)->toDateString());
});

test('creating a semiannual service advances next_charge_date by six months', function () {
    $project = createProjectForScheduling();

    $service = app(CreateServiceWithSchedule::class)->handle($project->client, [
        'name' => 'Mantenimiento semestral',
        'description' => null,
        'billing_frequency' => ServiceBillingFrequency::Semiannual,
        'amount' => '4200.00',
        'currency' => 'MXN',
        'status' => ServiceStatus::Activo,
        'starts_on' => now()->toDateString(),
        'installments_count' => null,
    ], $project);

    $service->refresh();

    expect($service->charges)->toHaveCount(1)
        ->and($service->next_charge_date->toDateString())->toBe(now()->addMonthsNoOverflow(6)->toDateString());
});
