<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('client users are redirected to the portal instead of the dashboard', function () {
    $clientUser = User::factory()->client()->create();
    $this->actingAs($clientUser);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('portal.projects.index'));
});
