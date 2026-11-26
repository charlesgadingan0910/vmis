<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the VMIS identity, purpose and help on the sign-in page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Vehicle Management Information System')
        ->assertSee('POLICE REGIONAL OFFICE 5')
        ->assertSee('Badge number or PNP e-mail')
        ->assertSee('Vehicle requests, approvals', false)
        ->assertSee('VMIS Driver app')
        ->assertSee('images/vmis-emblem.png', false);
});

it('shows the lockout message after five failed sign-ins', function () {
    $user = User::create([
        'account_type' => 'ADMINISTRATOR', 'lastname' => 'Tester', 'firstname' => 'Login', 'fullname' => 'Login Tester',
        'badge_number' => 'LGN-1', 'email' => 'login1@example.test', 'password' => 'secret-password',
        'is_active' => '1', 'is_password_changed' => '1',
    ]);

    foreach (range(1, 5) as $i) {
        $this->post(route('login.authenticate'), ['login' => $user->email, 'password' => 'wrong']);
    }

    $this->followingRedirects()
        ->post(route('login.authenticate'), ['login' => $user->email, 'password' => 'secret-password'])
        ->assertSee('Too many failed login attempts');
    $this->assertGuest();
});
