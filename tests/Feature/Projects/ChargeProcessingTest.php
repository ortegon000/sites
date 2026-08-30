<?php

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ChargeDueSoonNotification;
use App\Notifications\ChargeOverdueNotification;
use Illuminate\Support\Facades\Notification;

test('charges:process generates due recurring charges, marks overdue ones, and sends reminders only once', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create();
    $project = Project::factory()->for($client)->create();

    $dueSoonService = Service::factory()->monthly()->for($project)->create([
        'next_charge_date' => now()->toDateString(),
    ]);

    $overdueService = Service::factory()->monthly()->for($project)->create([
        'next_charge_date' => null,
    ]);

    $overdueCharge = Charge::factory()->for($overdueService)->create([
        'status' => ChargeStatus::Pendiente,
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $this->artisan('charges:process')->assertSuccessful();

    expect($dueSoonService->fresh()->charges)->toHaveCount(1)
        ->and($overdueCharge->fresh()->status)->toBe(ChargeStatus::Vencido);

    Notification::assertSentTo($admin, ChargeDueSoonNotification::class);
    Notification::assertSentTo($admin, ChargeOverdueNotification::class);

    $this->artisan('charges:process');

    Notification::assertSentToTimes($admin, ChargeDueSoonNotification::class, 1);
    Notification::assertSentToTimes($admin, ChargeOverdueNotification::class, 1);
});
