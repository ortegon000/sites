<?php

use App\Actions\Charges\GenerateScheduledCharges;
use App\Enums\ServiceStatus;
use App\Livewire\ServicesPanel;
use App\Models\Charge;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceInstallment;
use App\Models\User;
use Livewire\Livewire;

test('staff can delete a service that has no paid charges', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $service = Service::factory()->for($project)->create();
    $charge = Charge::factory()->for($service)->pending()->create();

    $this->actingAs($staff);

    Livewire::test(ServicesPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('deleteService', $service->id);

    expect(Service::find($service->id))->toBeNull()
        ->and(Charge::find($charge->id))->toBeNull();
});

test('a service with a paid charge is not deleted, so the payment record survives', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $service = Service::factory()->for($project)->create();
    $paidCharge = Charge::factory()->for($service)->paid()->create();

    $this->actingAs($staff);

    Livewire::test(ServicesPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('deleteService', $service->id);

    expect(Service::find($service->id))->not->toBeNull()
        ->and(Charge::find($paidCharge->id))->not->toBeNull();
});

test('cancelling a service keeps its charges and stops the schedule', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $service = Service::factory()->for($project)->monthly()->create();
    $paidCharge = Charge::factory()->for($service)->paid()->create();

    $this->actingAs($staff);

    Livewire::test(ServicesPanel::class, ['client' => $project->client, 'project' => $project])
        ->call('cancelService', $service->id);

    $service->refresh();

    expect($service->status)->toBe(ServiceStatus::Cancelado)
        ->and($service->next_charge_date)->toBeNull()
        ->and(Charge::find($paidCharge->id))->not->toBeNull();
});

test('only active services generate charges', function () {
    $project = Project::factory()->create();

    $paused = Service::factory()->for($project)->monthly()->create(['status' => ServiceStatus::Pausado]);
    $oneTime = Service::factory()->for($project)->oneTime()->create(['status' => ServiceStatus::Cancelado]);
    $installment = Service::factory()->for($project)->installment()->create(['status' => ServiceStatus::Completado]);
    ServiceInstallment::factory()->for($installment)->create([
        'installment_number' => 1,
        'due_date' => today()->toDateString(),
    ]);

    app(GenerateScheduledCharges::class)->handle();

    expect($paused->charges()->count())->toBe(0)
        ->and($oneTime->charges()->count())->toBe(0)
        ->and($installment->charges()->count())->toBe(0);
});

test('a collaborator cannot reach the services panel of a project', function () {
    $collaborator = User::factory()->collaborator()->create();
    $project = Project::factory()->create();
    $project->users()->attach($collaborator);
    $service = Service::factory()->for($project)->create();

    $this->actingAs($collaborator);

    Livewire::test(ServicesPanel::class, ['client' => $project->client, 'project' => $project])
        ->assertForbidden();

    expect(Service::find($service->id))->not->toBeNull();
});
