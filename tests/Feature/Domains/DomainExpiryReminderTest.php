<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\User;
use App\Notifications\DomainExpiringNotification;
use Illuminate\Support\Facades\Notification;

test('a managed domain expiring within a month notifies the admins', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $client = Client::factory()->client()->create();
    /** El dominio no cuelga de ningún proyecto, así que no hay equipo asignado a quién avisarle. */
    $staff = User::factory()->staff()->create();

    $domain = Domain::factory()->for($client)->create([
        'expires_at' => now()->addDays(10)->toDateString(),
    ]);

    $this->artisan('charges:process')->assertSuccessful();

    Notification::assertSentTo($admin, DomainExpiringNotification::class);
    Notification::assertNotSentTo($staff, DomainExpiringNotification::class);

    expect($domain->refresh()->expiry_notified_at)->not->toBeNull();
});

test('the same expiry is not announced twice', function () {
    Notification::fake();

    User::factory()->admin()->create();
    Domain::factory()->create(['expires_at' => now()->addDays(10)->toDateString()]);

    $this->artisan('charges:process')->assertSuccessful();
    $this->artisan('charges:process')->assertSuccessful();

    Notification::assertSentTimes(DomainExpiringNotification::class, 1);
});

test('renewing a domain arms the reminder again', function () {
    Notification::fake();

    User::factory()->admin()->create();
    $domain = Domain::factory()->create(['expires_at' => now()->addDays(10)->toDateString()]);

    $this->artisan('charges:process')->assertSuccessful();

    $domain->update(['expires_at' => now()->addDays(20)->toDateString()]);

    expect($domain->refresh()->expiry_notified_at)->toBeNull();

    $this->artisan('charges:process')->assertSuccessful();

    Notification::assertSentTimes(DomainExpiringNotification::class, 2);
});

test('domains we only track, expired ones and far-off ones are left alone', function () {
    Notification::fake();

    User::factory()->admin()->create();

    Domain::factory()->tracked()->create(['expires_at' => now()->addDays(10)->toDateString()]);
    Domain::factory()->expired()->create();
    Domain::factory()->create(['expires_at' => now()->addMonths(6)->toDateString()]);
    Domain::factory()->create(['expires_at' => null]);

    $this->artisan('charges:process')->assertSuccessful();

    Notification::assertNothingSentTo(User::all());
});

test('el dominio de un cliente sin trabajo abierto también llega a los admins', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    Domain::factory()->create(['expires_at' => now()->addDays(5)->toDateString()]);

    $this->artisan('charges:process')->assertSuccessful();

    Notification::assertSentTo($admin, DomainExpiringNotification::class);
});
