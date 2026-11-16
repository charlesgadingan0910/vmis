<?php

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

function secUser(string $accountType, array $attributes = []): User
{
    static $count = 0;
    $count++;

    return User::create(array_merge([
        'account_type'        => $accountType,
        'lastname'            => 'Tester',
        'firstname'           => 'Security',
        'fullname'            => 'Security Tester',
        'badge_number'        => 'SEC-' . $count,
        'email'               => "security{$count}@example.test",
        'password'            => 'secret-password',
        'is_active'           => '1',
        'is_password_changed' => '1',
    ], $attributes));
}

beforeEach(fn () => RateLimiter::clear('login|sec-lock|127.0.0.1'));

it('locks the web login after five wrong passwords, even for the right one', function () {
    $user = secUser('ADMINISTRATOR', ['badge_number' => 'SEC-LOCK']);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['login' => 'SEC-LOCK', 'password' => 'wrong'])->assertSessionHasErrors('login');
    }

    $this->post('/login', ['login' => 'SEC-LOCK', 'password' => 'secret-password'])
        ->assertSessionHasErrors('login');
    $this->assertGuest();

    // another account is unaffected
    $other = secUser('ADMINISTRATOR', ['badge_number' => 'SEC-OTHER']);
    $this->post('/login', ['login' => 'SEC-OTHER', 'password' => 'secret-password'])->assertRedirect();
    $this->assertAuthenticatedAs($other);
});

it('locks the mobile login with a 429 after five wrong passwords', function () {
    secUser('DRIVER', ['badge_number' => 'SEC-LOCK']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/login', ['login' => 'SEC-LOCK', 'password' => 'wrong'])->assertStatus(401);
    }

    $this->postJson('/api/v1/login', ['login' => 'SEC-LOCK', 'password' => 'secret-password'])->assertStatus(429);
});

it('renews a mobile token on use and rate limits the API', function () {
    $driver = secUser('DRIVER');
    $issued = ApiToken::generate($driver, 'Test phone');
    $issued['model']->forceFill(['expires_at' => now()->addDays(3)])->save();

    $response = $this->withToken($issued['plain'])->getJson('/api/v1/me')->assertOk();

    expect($response->headers->get('X-RateLimit-Limit'))->toBe('120');
    expect($issued['model']->fresh()->expires_at->diffInDays(now(), true))->toBeGreaterThan(85);
});

it('rejects an expired mobile token', function () {
    $driver = secUser('DRIVER');
    $issued = ApiToken::generate($driver, 'Old phone');
    $issued['model']->forceFill(['expires_at' => now()->subDay()])->save();

    $this->withToken($issued['plain'])->getJson('/api/v1/me')->assertStatus(401);
});

it('lets an administrator list and revoke a device, which then loses access', function () {
    $admin = secUser('SUPER ADMINISTRATOR');
    $driver = secUser('DRIVER');
    $issued = ApiToken::generate($driver, 'Lost phone');

    $this->actingAs($admin)->get('/devices')->assertOk()->assertSee('Lost phone');

    $this->actingAs($admin)->delete('/devices/' . $issued['model']->id)->assertRedirect();
    expect(ApiToken::count())->toBe(0);

    $this->withToken($issued['plain'])->getJson('/api/v1/me')->assertStatus(401);
});

it('blocks non-administrators from the device list', function () {
    $this->actingAs(secUser('VIEWER'))->get('/devices')->assertForbidden();
    $this->actingAs(secUser('UNIT ADMINISTRATOR'))->get('/devices')->assertForbidden();
});
