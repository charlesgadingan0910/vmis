<?php

use App\Models\ApiToken;
use App\Models\Driver;
use App\Models\FleetAlert;
use App\Models\FuelLog;
use App\Models\Station;
use App\Models\TripLog;
use App\Models\TripRegistration;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRegistration;
use App\Models\VehicleType;
use App\Services\FleetAlertSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| P1-07 — regression tests for VMIS's critical flows
|--------------------------------------------------------------------------
| 1. Role scoping (Vehicle Inventory)   3. Trip registration workflow
| 2. Fleet alert sync + visibility      4. Driver mobile API
*/

function flowUser(string $accountType, array $attributes = []): User
{
    static $count = 0;
    $count++;

    return User::create(array_merge([
        'account_type'        => $accountType,
        'lastname'            => 'Tester',
        'firstname'           => 'Flow',
        'fullname'            => 'Flow Tester',
        'badge_number'        => 'FLW-' . $count,
        'email'               => "flow{$count}@example.test",
        'password'            => 'secret-password',
        'is_active'           => '1',
        'is_password_changed' => '1',
    ], $attributes));
}

/**
 * Two units (Alpha, Bravo), one station each, one serviceable vehicle each.
 *
 * @return array{units: array<int, Unit>, stations: array<int, Station>, vehicles: array<int, Vehicle>}
 */
function flowFleet(): array
{
    $type = VehicleType::create(['name' => 'Patrol Car']);
    $units = $stations = $vehicles = [];

    foreach (['Alpha', 'Bravo'] as $i => $name) {
        $units[$i] = Unit::create(['unit_name' => $name . ' Unit', 'unit_abbvr' => strtoupper($name)]);
        $stations[$i] = Station::create(['unit_id' => $units[$i]->id, 'station_name' => $name . ' Station', 'station_abbvr' => strtoupper($name) . 'S']);
        $vehicles[$i] = Vehicle::create([
            'plate_number'    => strtoupper($name) . ' 1234',
            'make'            => 'TOYOTA',
            'model'           => 'HILUX',
            'vehicle_type_id' => $type->id,
            'unit_id'         => $units[$i]->id,
            'station_id'      => $stations[$i]->id,
            'status'          => 'SERVICEABLE',
        ]);
    }

    return compact('units', 'stations', 'vehicles');
}

function flowDriver(array $attributes = []): Driver
{
    static $n = 0;
    $n++;

    return Driver::create(array_merge([
        'firstname' => 'Driver' . $n, 'lastname' => 'Test' . $n, 'status' => 'active',
        'license_expiration_date' => now()->addYears(2)->toDateString(),
    ], $attributes));
}

function flowAjaxGet($test, string $uri)
{
    return $test->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get($uri);
}

/* ------------------------------------------------------------------ */
/* 1. Role scoping                                                     */
/* ------------------------------------------------------------------ */

it('shows each role only the vehicles it may see in Vehicle Inventory', function () {
    $f = flowFleet();

    $content = fn ($user) => flowAjaxGet($this->actingAs($user), '/vehicles')->assertOk()->getContent();

    $all = $content(flowUser('SUPER ADMINISTRATOR'));
    expect($all)->toContain('ALPHA 1234')->toContain('BRAVO 1234');

    $unit = $content(flowUser('UNIT ADMINISTRATOR', ['unit_id' => $f['units'][0]->id]));
    expect($unit)->toContain('ALPHA 1234')->not->toContain('BRAVO 1234');

    $station = $content(flowUser('STATION ADMINISTRATOR', ['unit_id' => $f['units'][1]->id, 'station_id' => $f['stations'][1]->id]));
    expect($station)->toContain('BRAVO 1234')->not->toContain('ALPHA 1234');

    $viewer = $content(flowUser('VIEWER'));
    expect($viewer)->toContain('ALPHA 1234')->toContain('BRAVO 1234');
});

it('blocks Driver accounts from Vehicle Inventory', function () {
    flowFleet();

    $this->actingAs(flowUser('DRIVER'))->get('/vehicles')->assertForbidden();
});

it('keeps Viewers read-only and unit admins inside their own unit when deleting', function () {
    $f = flowFleet();

    $this->actingAs(flowUser('VIEWER'))->delete('/vehicles/' . $f['vehicles'][0]->id)->assertForbidden();

    $unitAdmin = flowUser('UNIT ADMINISTRATOR', ['unit_id' => $f['units'][0]->id]);
    $this->actingAs($unitAdmin)->delete('/vehicles/' . $f['vehicles'][1]->id)->assertForbidden();
    expect(Vehicle::count())->toBe(2);

    // P2-01: a unit admin's delete is held for approval, a Super Administrator's is immediate.
    $this->actingAs($unitAdmin)->delete('/vehicles/' . $f['vehicles'][0]->id)->assertRedirect();
    expect(Vehicle::count())->toBe(2)->and(\App\Models\ChangeRequest::where('status', 'pending')->count())->toBe(1);

    $this->actingAs(flowUser('SUPER ADMINISTRATOR'))->delete('/vehicles/' . $f['vehicles'][0]->id)->assertRedirect();
    expect(Vehicle::count())->toBe(1);
});

/* ------------------------------------------------------------------ */
/* 2. Fleet alert sync                                                 */
/* ------------------------------------------------------------------ */

function flowAlertScenario(): array
{
    $f = flowFleet();
    $a = $f['vehicles'][0];

    VehicleRegistration::create(['vehicle_id' => $a->id, 'registration_year' => (int) date('Y'), 'expiry_date' => now()->addDays(10)->toDateString(), 'uploaded_by' => flowUser('SUPER ADMINISTRATOR')->id]);
    $a->update(['next_pms_date' => now()->subDays(5)->toDateString()]);

    $b = $f['vehicles'][1];
    $b->update(['status' => 'UNSERVICEABLE', 'unserviceable_since' => now()->subDays(100)->toDateString()]);

    $driver = flowDriver(['license_expiration_date' => now()->subDays(3)->toDateString()]);

    return $f + ['driver' => $driver];
}

it('creates registration, PMS, unserviceable and license alerts', function () {
    $s = flowAlertScenario();

    app(FleetAlertSyncService::class)->sync();

    expect(FleetAlert::where('type', FleetAlert::TYPE_REGISTRATION_DUE)->where('vehicle_id', $s['vehicles'][0]->id)->exists())->toBeTrue()
        ->and(FleetAlert::where('type', FleetAlert::TYPE_PMS_DUE)->where('vehicle_id', $s['vehicles'][0]->id)->first()?->severity)->toBe(FleetAlert::SEVERITY_OVERDUE)
        ->and(FleetAlert::where('type', FleetAlert::TYPE_UNSERVICEABLE)->where('vehicle_id', $s['vehicles'][1]->id)->exists())->toBeTrue()
        ->and(FleetAlert::where('type', FleetAlert::TYPE_LICENSE_EXPIRY)->where('driver_id', $s['driver']->id)->first()?->severity)->toBe(FleetAlert::SEVERITY_OVERDUE);
});

it('does not duplicate alerts when the sync runs again, and clears fixed ones', function () {
    $s = flowAlertScenario();

    app(FleetAlertSyncService::class)->sync();
    $first = FleetAlert::count();
    app(FleetAlertSyncService::class)->sync();
    expect(FleetAlert::count())->toBe($first);

    // fix the PMS date and the license → those alerts disappear
    $s['vehicles'][0]->update(['next_pms_date' => now()->addYear()->toDateString()]);
    $s['driver']->update(['license_expiration_date' => now()->addYears(3)->toDateString()]);
    app(FleetAlertSyncService::class)->sync();

    expect(FleetAlert::where('type', FleetAlert::TYPE_PMS_DUE)->exists())->toBeFalse()
        ->and(FleetAlert::where('type', FleetAlert::TYPE_LICENSE_EXPIRY)->exists())->toBeFalse()
        ->and(FleetAlert::where('type', FleetAlert::TYPE_REGISTRATION_DUE)->exists())->toBeTrue();
});

it('scopes alert visibility by role', function () {
    $s = flowAlertScenario();
    app(FleetAlertSyncService::class)->sync();

    $unitAdmin = flowUser('UNIT ADMINISTRATOR', ['unit_id' => $s['units'][0]->id]);
    $visible = FleetAlert::visibleTo($unitAdmin)->pluck('vehicle_id')->filter()->unique()->values()->all();
    expect($visible)->toBe([$s['vehicles'][0]->id]);

    expect(FleetAlert::visibleTo(flowUser('SUPER ADMINISTRATOR'))->where('type', FleetAlert::TYPE_UNSERVICEABLE)->count())->toBe(1);

    // a driver sees their own license alert only
    $driverUser = flowUser('DRIVER', ['driver_id' => $s['driver']->id]);
    $mine = FleetAlert::visibleTo($driverUser)->get();
    expect($mine)->toHaveCount(1)->and($mine->first()->type)->toBe(FleetAlert::TYPE_LICENSE_EXPIRY);
});

/* ------------------------------------------------------------------ */
/* 3. Trip registration workflow                                       */
/* ------------------------------------------------------------------ */

function flowTripPayload(array $f, Driver $driver, array $extra = []): array
{
    return array_merge([
        'vehicle_id'  => $f['vehicles'][0]->id,
        'driver_id'   => $driver->id,
        'trip_type'   => 'unscheduled',
        'origin'      => 'Legazpi',
        'destination' => 'Naga',
    ], $extra);
}

it('registers, approves and rejects trips', function () {
    $f = flowFleet();
    $admin = flowUser('SUPER ADMINISTRATOR');
    $driver = flowDriver();

    $this->actingAs($admin)->postJson('/trip-registrations', flowTripPayload($f, $driver))->assertOk()->assertJson(['success' => true]);
    $reg = TripRegistration::firstOrFail();
    expect($reg->status)->toBe('pending_approval');

    $this->actingAs($admin)->postJson("/trip-registrations/{$reg->id}/approve")->assertOk();
    expect($reg->fresh()->status)->toBe('approved')->and($reg->fresh()->approved_by)->toBe($admin->id);

    // an approved registration can't be approved again
    $this->actingAs($admin)->postJson("/trip-registrations/{$reg->id}/approve")->assertStatus(422);

    $this->actingAs($admin)->postJson('/trip-registrations', flowTripPayload($f, $driver))->assertOk();
    $second = TripRegistration::latest('id')->first();
    $this->actingAs($admin)->postJson("/trip-registrations/{$second->id}/reject", ['rejection_reason' => 'No fuel budget'])->assertOk();
    expect($second->fresh()->status)->toBe('rejected')->and($second->fresh()->rejection_reason)->toBe('No fuel budget');
});

it('auto-approves emergency trips and can cancel a pending one', function () {
    $f = flowFleet();
    $admin = flowUser('ADMINISTRATOR');
    $driver = flowDriver();

    $this->actingAs($admin)->postJson('/trip-registrations', flowTripPayload($f, $driver, ['trip_type' => 'emergency']))->assertOk();
    expect(TripRegistration::first()->status)->toBe('approved');

    $this->actingAs($admin)->postJson('/trip-registrations', flowTripPayload($f, $driver))->assertOk();
    $pending = TripRegistration::where('status', 'pending_approval')->firstOrFail();
    $this->actingAs($admin)->postJson("/trip-registrations/{$pending->id}/cancel")->assertOk();
    expect($pending->fresh()->status)->toBe('cancelled');
});

it('refuses a trip for a driver whose license has expired', function () {
    $f = flowFleet();
    $driver = flowDriver(['license_expiration_date' => now()->subDay()->toDateString()]);

    $this->actingAs(flowUser('SUPER ADMINISTRATOR'))
        ->postJson('/trip-registrations', flowTripPayload($f, $driver))
        ->assertStatus(422);

    expect(TripRegistration::count())->toBe(0);
});

it('keeps trip registration inside the admin scope and blocks Viewers and Drivers', function () {
    $f = flowFleet();
    $driver = flowDriver();

    $stationAdmin = flowUser('STATION ADMINISTRATOR', ['unit_id' => $f['units'][1]->id, 'station_id' => $f['stations'][1]->id]);
    // vehicle[0] belongs to the other station
    $this->actingAs($stationAdmin)->postJson('/trip-registrations', flowTripPayload($f, $driver))->assertStatus(403);

    $this->actingAs(flowUser('VIEWER'))->postJson('/trip-registrations', flowTripPayload($f, $driver))->assertForbidden();
    $this->actingAs(flowUser('DRIVER'))->postJson('/trip-registrations', flowTripPayload($f, $driver))->assertForbidden();

    // a unit admin cannot approve another unit's registration
    $reg = TripRegistration::create([
        'vehicle_id' => $f['vehicles'][0]->id, 'driver_id' => $driver->id, 'trip_type' => 'unscheduled',
        'origin' => 'A', 'destination' => 'B', 'status' => 'pending_approval', 'round_trip' => false,
        'registered_by' => flowUser('SUPER ADMINISTRATOR')->id,
    ]);
    $otherUnitAdmin = flowUser('UNIT ADMINISTRATOR', ['unit_id' => $f['units'][1]->id]);
    $this->actingAs($otherUnitAdmin)->postJson("/trip-registrations/{$reg->id}/approve")->assertForbidden();
    expect($reg->fresh()->status)->toBe('pending_approval');
});

/* ------------------------------------------------------------------ */
/* 4. Driver mobile API                                                */
/* ------------------------------------------------------------------ */

/** @return array{token: string, driver: Driver, user: User, f: array} */
function flowApiDriver(): array
{
    $f = flowFleet();
    $driver = flowDriver();
    $user = flowUser('DRIVER', ['driver_id' => $driver->id]);
    $f['vehicles'][0]->update(['assigned_driver_id' => $driver->id]);

    return ['token' => ApiToken::generate($user, 'Test phone')['plain'], 'driver' => $driver, 'user' => $user, 'f' => $f];
}

it('logs a driver in, and rejects a wrong password and non-mobile accounts', function () {
    $user = flowUser('DRIVER', ['badge_number' => 'FLW-LOGIN']);

    $this->postJson('/api/v1/login', ['login' => 'FLW-LOGIN', 'password' => 'secret-password', 'device_name' => 'Android'])
        ->assertOk()->assertJson(['success' => true, 'token_type' => 'Bearer'])->assertJsonStructure(['token']);

    $this->postJson('/api/v1/login', ['login' => 'FLW-LOGIN', 'password' => 'nope'])->assertStatus(401);

    flowUser('ADMINISTRATOR', ['badge_number' => 'FLW-ADMIN']);
    $this->postJson('/api/v1/login', ['login' => 'FLW-ADMIN', 'password' => 'secret-password'])->assertStatus(403);

    expect(ApiToken::where('user_id', $user->id)->count())->toBe(1);
});

it('requires a token for the API', function () {
    $this->getJson('/api/v1/me')->assertStatus(401);
    $this->withToken('not-a-real-token')->getJson('/api/v1/me')->assertStatus(401);
});

it('shows a driver only their own assigned vehicle', function () {
    $d = flowApiDriver();

    $response = $this->withToken($d['token'])->getJson('/api/v1/vehicle')->assertOk();

    expect($response->getContent())->toContain('ALPHA 1234')->not->toContain('BRAVO 1234');
});

it('lets a driver log a trip and a refuel for their own vehicle', function () {
    $d = flowApiDriver();

    $this->withToken($d['token'])->postJson('/api/v1/trips', [
        'trip_date' => now()->toDateString(), 'origin' => 'Legazpi', 'destination' => 'Naga',
        'odometer_start' => 100, 'odometer_end' => 160,
    ])->assertSuccessful();

    $trip = TripLog::firstOrFail();
    expect($trip->driver_id)->toBe($d['driver']->id)->and($trip->vehicle_id)->toBe($d['f']['vehicles'][0]->id);

    $this->withToken($d['token'])->postJson('/api/v1/fuel-logs', [
        'refuel_date' => now()->toDateString(), 'liters' => 40.5, 'total_cost' => 2500, 'odometer_reading' => 500,
    ])->assertSuccessful();

    expect(FuelLog::count())->toBe(1);
});

it('validates trip input and keeps a driver without a profile out', function () {
    $d = flowApiDriver();

    $this->withToken($d['token'])->postJson('/api/v1/trips', ['trip_date' => now()->toDateString()])
        ->assertStatus(422);
    expect(TripLog::count())->toBe(0);

    $unlinked = flowUser('DRIVER');
    $token = ApiToken::generate($unlinked, 'No profile')['plain'];
    $this->withToken($token)->postJson('/api/v1/trips', [
        'trip_date' => now()->toDateString(), 'origin' => 'A', 'destination' => 'B',
    ])->assertStatus(403);
});

it('keeps Viewers read-only and service history Viewer-only on the API', function () {
    flowFleet();
    $viewerToken = ApiToken::generate(flowUser('VIEWER'), 'Viewer phone')['plain'];
    $driverToken = ApiToken::generate(flowUser('DRIVER'), 'Driver phone')['plain'];

    $this->withToken($viewerToken)->postJson('/api/v1/trips', [
        'trip_date' => now()->toDateString(), 'origin' => 'A', 'destination' => 'B',
    ])->assertForbidden();

    $this->withToken($viewerToken)->getJson('/api/v1/service-history')->assertOk();
    $this->withToken($driverToken)->getJson('/api/v1/service-history')->assertForbidden();
});

it('gives a driver only their own notifications and lets them mark one read', function () {
    $d = flowApiDriver();
    // the notifications endpoint re-syncs when stale; mark it fresh so the hand-made alerts below survive
    Cache::put('fleet_alerts:last_sync', true, now()->addMinutes(5));

    $mine = FleetAlert::create(['type' => FleetAlert::TYPE_PMS_DUE, 'severity' => 'overdue', 'title' => 'Mine', 'body' => 'x',
        'vehicle_id' => $d['f']['vehicles'][0]->id, 'dedupe_key' => 'test:mine']);
    $other = FleetAlert::create(['type' => FleetAlert::TYPE_PMS_DUE, 'severity' => 'overdue', 'title' => 'Other', 'body' => 'x',
        'vehicle_id' => $d['f']['vehicles'][1]->id, 'dedupe_key' => 'test:other']);

    $response = $this->withToken($d['token'])->getJson('/api/v1/notifications')->assertOk();
    expect($response->getContent())->toContain('Mine')->not->toContain('Other');

    $this->withToken($d['token'])->postJson("/api/v1/notifications/{$other->id}/read")->assertStatus(404);
    $this->withToken($d['token'])->postJson("/api/v1/notifications/{$mine->id}/read")->assertOk();
    expect($mine->fresh()->read_at)->not->toBeNull();
});
