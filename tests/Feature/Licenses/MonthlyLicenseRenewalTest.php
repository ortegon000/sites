<?php

use App\Enums\RenewalStatus;
use App\Models\License;
use App\Models\Renewal;
use App\Models\User;
use App\Notifications\LicenseRenewalDueNotification;
use Illuminate\Support\Facades\Notification;

test('a monthly license due soon notifies the admins, not the client', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $license = License::factory()->monthly()->create([
        'renewal_date' => now()->addDays(2)->toDateString(),
    ]);

    $this->artisan('charges:process')->assertSuccessful();

    Notification::assertSentTo($admin, LicenseRenewalDueNotification::class);

    expect($license->refresh()->expiry_notified_at)->not->toBeNull()
        ->and(Renewal::where('renewable_type', License::class)->where('renewable_id', $license->id)->exists())->toBeFalse();
});

test('a monthly license is not announced twice for the same due date', function () {
    Notification::fake();

    User::factory()->admin()->create();
    License::factory()->monthly()->create(['renewal_date' => now()->addDays(2)->toDateString()]);

    $this->artisan('charges:process')->assertSuccessful();
    $this->artisan('charges:process')->assertSuccessful();

    Notification::assertSentTimes(LicenseRenewalDueNotification::class, 1);
});

test('a past due monthly license rolls to next month and can be reminded again', function () {
    Notification::fake();

    User::factory()->admin()->create();
    $license = License::factory()->monthly()->create([
        'renewal_date' => now()->subDay()->toDateString(),
        'expiry_notified_at' => now()->subDays(5),
    ]);

    $this->artisan('charges:process')->assertSuccessful();

    expect($license->refresh()->renewal_date->toDateString())->toBe(now()->subDay()->addMonthNoOverflow()->toDateString())
        ->and($license->expiry_notified_at)->toBeNull();
});

test('a monthly license far from its due date is left alone', function () {
    Notification::fake();

    User::factory()->admin()->create();
    License::factory()->monthly()->create(['renewal_date' => now()->addDays(20)->toDateString()]);

    $this->artisan('charges:process')->assertSuccessful();

    Notification::assertNothingSentTo(User::all());
});

test('an annual license still opens a renewal cycle for the client to be notified', function () {
    $license = License::factory()->create(['renewal_date' => now()->addDays(10)->toDateString()]);

    $this->artisan('charges:process')->assertSuccessful();

    $renewal = Renewal::where('renewable_type', License::class)->where('renewable_id', $license->id)->first();

    expect($renewal)->not->toBeNull()
        ->and(RenewalStatus::open())->toContain($renewal->status);
});
