<?php

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Station;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dspUser(string $accountType, array $attributes = []): User
{
    static $count = 0;
    $count++;

    return User::create(array_merge([
        'account_type' => $accountType, 'lastname' => 'Tester', 'firstname' => 'Dispatch',
        'fullname' => 'Dispatch Tester ' . $count, 'badge_number' => 'DSP-' . $count,
        'email' => "dispatch{$count}@example.test", 'password' => 'secret-password',
        'is_active' => '1', 'is_password_changed' => '1',
    ], $attributes));
}

function dspWorld(): array
{
    $type = VehicleType::create(['name' => 'Patrol Car']);
    $units = $stations = $vehicles = [];
    foreach (['Alpha', 'Bravo'] as $i => $name) {
        $units[$i] = Unit::create(['unit_name' => $name . ' Unit', 'unit_abbvr' => strtoupper($name)]);
        $stations[$i] = Station::create(['unit_id' => $units[$i]->id, 'station_name' => $name . ' Station', 'station_abbvr' => strtoupper($name) . 'S']);
        $vehicles[$i] = Vehicle::create([
            'plate_number' => strtoupper($name) . ' 1234', 'make' => 'TOYOTA', 'model' => 'HILUX', 'vehicle_type_id' => $type->id,
            'unit_id' => $units[$i]->id, 'station_id' => $stations[$i]->id, 'status' => 'SERVICEABLE', 'source' => 'ORGANIC',
        ]);
    }
    $driver = fn ($first) => Driver::create(['firstname' => $first, 'lastname' => 'Cruz', 'status' => 'active', 'license_expiration_date' => now()->addYears(2)->toDateString()]);

    return [
        'units' => $units, 'stations' => $stations, 'vehicles' => $vehicles,
        'driver' => $driver('Juan'), 'driver2' => $driver('Pedro'),
        'stationAdmin' => dspUser('STATION ADMINISTRATOR', ['unit_id' => $units[0]->id, 'station_id' => $stations[0]->id]),
        'unitAdmin' => dspUser('UNIT ADMINISTRATOR', ['unit_id' => $units[0]->id]),
        'otherUnitAdmin' => dspUser('UNIT ADMINISTRATOR', ['unit_id' => $units[1]->id]),
        'admin' => dspUser('ADMINISTRATOR'),
        'super' => dspUser('SUPER ADMINISTRATOR'),
    ];
}

function dspPayload(Vehicle $vehicle, array $o = []): array
{
    return array_merge([
        'vehicle_id' => $vehicle->id, 'start_at' => now('Asia/Manila')->addDays(2)->format('Y-m-d') . 'T08:00',
        'end_at' => now('Asia/Manila')->addDays(2)->format('Y-m-d') . 'T17:00',
        'purpose' => 'Court hearing', 'destination' => 'Legazpi City',
    ], $o);
}

it('lets a station admin request a vehicle and a unit admin approve it', function () {
    $w = dspWorld();

    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][0], ['driver_id' => $w['driver']->id]))->assertSessionHas('success');
    $req = VehicleRequest::first();
    expect($req->status)->toBe('pending');

    $this->actingAs($w['unitAdmin'])->post("/dispatch/{$req->id}/approve")->assertSessionHas('success');

    expect($req->fresh()->status)->toBe('approved')
        ->and($req->fresh()->decided_by)->toBe($w['unitAdmin']->id);
    expect(ActivityLog::where('module', 'Dispatch')->count())->toBeGreaterThanOrEqual(2);
});

it('approves a Super Administrator request immediately', function () {
    $w = dspWorld();
    $this->actingAs($w['super'])->post('/dispatch', dspPayload($w['vehicles'][0]));

    expect(VehicleRequest::first()->status)->toBe('approved');
});

it('blocks a request that overlaps an approved booking of the same vehicle', function () {
    $w = dspWorld();
    $this->actingAs($w['super'])->post('/dispatch', dspPayload($w['vehicles'][0]));

    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][0], [
        'start_at' => now('Asia/Manila')->addDays(2)->format('Y-m-d') . 'T12:00',
        'end_at' => now('Asia/Manila')->addDays(2)->format('Y-m-d') . 'T20:00',
    ]))->assertSessionHas('error');

    expect(VehicleRequest::count())->toBe(1);
});

it('allows back-to-back bookings of the same vehicle', function () {
    $w = dspWorld();
    $this->actingAs($w['super'])->post('/dispatch', dspPayload($w['vehicles'][0]));

    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][0], [
        'start_at' => now('Asia/Manila')->addDays(2)->format('Y-m-d') . 'T17:00',
        'end_at' => now('Asia/Manila')->addDays(2)->format('Y-m-d') . 'T20:00',
    ]))->assertSessionHas('success');

    expect(VehicleRequest::count())->toBe(2);
});

it('blocks approving a request that now clashes, and the driver clashes too', function () {
    $w = dspWorld();
    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][0]));
    $pending = VehicleRequest::first();

    // Someone else books the same slot and is approved first.
    $this->actingAs($w['super'])->post('/dispatch', dspPayload($w['vehicles'][0]));

    $this->actingAs($w['unitAdmin'])->post("/dispatch/{$pending->id}/approve")->assertSessionHas('error');
    expect($pending->fresh()->status)->toBe('pending');

    // Driver double-booking on a different vehicle is also refused.
    $this->actingAs($w['super'])->post('/dispatch', dspPayload($w['vehicles'][1], ['driver_id' => $w['driver']->id]));
    $third = Vehicle::create([
        'plate_number' => 'ALPHA 7777', 'make' => 'TOYOTA', 'model' => 'VIOS', 'vehicle_type_id' => $w['vehicles'][0]->vehicle_type_id,
        'unit_id' => $w['units'][0]->id, 'station_id' => $w['stations'][0]->id, 'status' => 'SERVICEABLE', 'source' => 'ORGANIC',
    ]);
    $this->actingAs($w['super'])->post('/dispatch', dspPayload($third, ['driver_id' => $w['driver']->id]))->assertSessionHas('error');
});

it('lets the approver change the vehicle and driver', function () {
    $w = dspWorld();
    $other = Vehicle::create([
        'plate_number' => 'ALPHA 9999', 'make' => 'TOYOTA', 'model' => 'VIOS', 'vehicle_type_id' => $w['vehicles'][0]->vehicle_type_id,
        'unit_id' => $w['units'][0]->id, 'station_id' => $w['stations'][0]->id, 'status' => 'SERVICEABLE', 'source' => 'ORGANIC',
    ]);
    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][0]));
    $req = VehicleRequest::first();

    $this->actingAs($w['unitAdmin'])->post("/dispatch/{$req->id}/approve", ['vehicle_id' => $other->id, 'driver_id' => $w['driver2']->id])->assertSessionHas('success');

    expect($req->fresh()->vehicle_id)->toBe($other->id)->and($req->fresh()->driver_id)->toBe($w['driver2']->id);
});

it('keeps roles in their lane', function () {
    $w = dspWorld();
    $driverUser = dspUser('DRIVER');
    $viewer = dspUser('VIEWER');

    $this->actingAs($driverUser)->get('/dispatch')->assertForbidden();
    $this->actingAs($viewer)->get('/dispatch')->assertOk();
    $this->actingAs($viewer)->post('/dispatch', dspPayload($w['vehicles'][0]))->assertSessionHas('error');

    // A station admin cannot request another station's/unit's vehicle.
    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][1]))->assertSessionHas('error');

    // A unit admin of another unit cannot decide, and nobody approves their own request.
    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][0]));
    $req = VehicleRequest::first();
    $this->actingAs($w['otherUnitAdmin'])->post("/dispatch/{$req->id}/approve")->assertForbidden();
    $this->actingAs($w['stationAdmin'])->post("/dispatch/{$req->id}/approve")->assertForbidden();
    expect($req->fresh()->status)->toBe('pending');
});

it('refuses past dates, bad ranges and expired licences, and lets a requester cancel', function () {
    $w = dspWorld();
    $expired = Driver::create(['firstname' => 'Old', 'lastname' => 'Licence', 'status' => 'active', 'license_expiration_date' => now()->subDay()->toDateString()]);

    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][0], ['start_at' => '2020-01-01T08:00', 'end_at' => '2020-01-01T09:00']))->assertSessionHas('error');
    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][0], ['end_at' => now('Asia/Manila')->addDays(2)->format('Y-m-d') . 'T07:00']))->assertSessionHas('error');
    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][0], ['driver_id' => $expired->id]))->assertSessionHas('error');
    expect(VehicleRequest::count())->toBe(0);

    $this->actingAs($w['stationAdmin'])->post('/dispatch', dspPayload($w['vehicles'][0]));
    $req = VehicleRequest::first();
    $this->actingAs($w['unitAdmin'])->post("/dispatch/{$req->id}/cancel")->assertForbidden();
    $this->actingAs($w['stationAdmin'])->post("/dispatch/{$req->id}/cancel")->assertSessionHas('success');
    expect($req->fresh()->status)->toBe('cancelled');
});
