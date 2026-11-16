<?php

use App\Models\ActivityLog;
use App\Models\ChangeRequest;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Station;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function apvUser(string $accountType, array $attributes = []): User
{
    static $count = 0;
    $count++;

    return User::create(array_merge([
        'account_type'        => $accountType,
        'lastname'            => 'Tester',
        'firstname'           => 'Approval',
        'fullname'            => 'Approval Tester ' . $count,
        'badge_number'        => 'APV-' . $count,
        'email'               => "approval{$count}@example.test",
        'password'            => 'secret-password',
        'is_active'           => '1',
        'is_password_changed' => '1',
    ], $attributes));
}

/** Two units with one station and one vehicle each, plus the people who act on them. */
function apvWorld(): array
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

    return [
        'units' => $units, 'stations' => $stations, 'vehicles' => $vehicles,
        'driver'       => Driver::create(['firstname' => 'Juan', 'lastname' => 'Cruz', 'status' => 'active', 'license_expiration_date' => now()->addYears(2)->toDateString()]),
        'stationAdmin' => apvUser('STATION ADMINISTRATOR', ['unit_id' => $units[0]->id, 'station_id' => $stations[0]->id]),
        'unitAdmin'    => apvUser('UNIT ADMINISTRATOR', ['unit_id' => $units[0]->id]),
        'otherUnitAdmin' => apvUser('UNIT ADMINISTRATOR', ['unit_id' => $units[1]->id]),
        'admin'        => apvUser('ADMINISTRATOR'),
        'super'        => apvUser('SUPER ADMINISTRATOR'),
    ];
}

function apvAssign($test, User $by, Vehicle $vehicle, Driver $driver)
{
    return $test->actingAs($by)->postJson("/driver-assignments/{$vehicle->id}/assign", ['driver_id' => $driver->id]);
}

function apvEditPayload(Vehicle $vehicle, array $overrides = []): array
{
    return array_merge([
        'plate_number' => $vehicle->plate_number, 'make' => 'TOYOTA', 'model' => 'HILUX',
        'vehicle_type_id' => $vehicle->vehicle_type_id, 'station_id' => $vehicle->station_id,
        'source' => 'ORGANIC', 'status' => 'SERVICEABLE',
    ], $overrides);
}

it('applies a Super Administrator assignment straight away', function () {
    $w = apvWorld();

    apvAssign($this, $w['super'], $w['vehicles'][0], $w['driver'])->assertOk()->assertJson(['success' => true]);

    expect($w['vehicles'][0]->fresh()->assigned_driver_id)->toBe($w['driver']->id)
        ->and(ChangeRequest::count())->toBe(0);
});

it('holds a Station Administrator assignment until a higher role approves it', function () {
    $w = apvWorld();

    apvAssign($this, $w['stationAdmin'], $w['vehicles'][0], $w['driver'])
        ->assertOk()->assertJson(['success' => true, 'pending' => true]);

    expect($w['vehicles'][0]->fresh()->assigned_driver_id)->toBeNull();
    $request = ChangeRequest::firstOrFail();
    expect($request->status)->toBe('pending')->and($request->category)->toBe('assignment')
        ->and($request->requester_role)->toBe('STATION ADMINISTRATOR');

    // the Unit Administrator of that unit approves → the assignment is applied
    $this->actingAs($w['unitAdmin'])->from('/approvals')->post(route('approvals.approve', $request))
        ->assertRedirect()->assertSessionHas('success');

    expect($w['vehicles'][0]->fresh()->assigned_driver_id)->toBe($w['driver']->id)
        ->and($request->fresh()->status)->toBe('approved')
        ->and($request->fresh()->decided_by)->toBe($w['unitAdmin']->id)
        ->and(DriverAssignment::where('vehicle_id', $w['vehicles'][0]->id)->where('driver_id', $w['driver']->id)->exists())->toBeTrue()
        ->and(ActivityLog::where('module', 'Approval')->count())->toBe(2)           // requested + approved
        ->and(ActivityLog::where('module', 'Driver Assignment')->count())->toBe(1);  // the change itself
});

it('lets only a higher role in the right unit approve', function () {
    $w = apvWorld();
    apvAssign($this, $w['stationAdmin'], $w['vehicles'][0], $w['driver'])->assertOk();
    $request = ChangeRequest::firstOrFail();

    $this->actingAs($w['otherUnitAdmin'])->post(route('approvals.approve', $request))->assertForbidden();
    $this->actingAs($w['stationAdmin'])->post(route('approvals.approve', $request))->assertForbidden();
    $this->actingAs(apvUser('VIEWER'))->post(route('approvals.approve', $request))->assertForbidden();

    expect($request->fresh()->status)->toBe('pending')->and($w['vehicles'][0]->fresh()->assigned_driver_id)->toBeNull();

    // an Administrator (higher, any unit) can
    $this->actingAs($w['admin'])->post(route('approvals.approve', $request))->assertRedirect();
    expect($request->fresh()->status)->toBe('approved');
});

it('never lets someone approve their own request or a peer\'s', function () {
    $w = apvWorld();

    apvAssign($this, $w['unitAdmin'], $w['vehicles'][0], $w['driver'])->assertOk()->assertJson(['pending' => true]);
    $request = ChangeRequest::firstOrFail();

    $this->actingAs($w['unitAdmin'])->post(route('approvals.approve', $request))->assertForbidden();
    $this->actingAs($w['otherUnitAdmin'])->post(route('approvals.approve', $request))->assertForbidden();

    $this->actingAs($w['super'])->post(route('approvals.approve', $request))->assertRedirect();
    expect($w['vehicles'][0]->fresh()->assigned_driver_id)->toBe($w['driver']->id);
});

it('leaves everything unchanged when a request is rejected, and lets the requester cancel', function () {
    $w = apvWorld();
    apvAssign($this, $w['stationAdmin'], $w['vehicles'][0], $w['driver'])->assertOk();
    $request = ChangeRequest::firstOrFail();

    $this->actingAs($w['unitAdmin'])->post(route('approvals.reject', $request), ['note' => 'Wrong driver'])->assertRedirect();
    expect($request->fresh()->status)->toBe('rejected')->and($request->fresh()->decision_note)->toBe('Wrong driver')
        ->and($w['vehicles'][0]->fresh()->assigned_driver_id)->toBeNull();

    apvAssign($this, $w['stationAdmin'], $w['vehicles'][0], $w['driver'])->assertOk();
    $second = ChangeRequest::latest('id')->first();
    $this->actingAs($w['unitAdmin'])->post(route('approvals.cancel', $second))->assertForbidden();
    $this->actingAs($w['stationAdmin'])->post(route('approvals.cancel', $second))->assertRedirect();
    expect($second->fresh()->status)->toBe('cancelled');
});

it('blocks a second pending assignment request for the same vehicle', function () {
    $w = apvWorld();

    apvAssign($this, $w['stationAdmin'], $w['vehicles'][0], $w['driver'])->assertOk();
    apvAssign($this, $w['stationAdmin'], $w['vehicles'][0], $w['driver'])->assertStatus(422);

    expect(ChangeRequest::count())->toBe(1);
});

it('keeps the request pending when the change can no longer be applied', function () {
    $w = apvWorld();
    apvAssign($this, $w['stationAdmin'], $w['vehicles'][0], $w['driver'])->assertOk();
    $request = ChangeRequest::firstOrFail();

    // before approval the driver's license expires
    $w['driver']->update(['license_expiration_date' => now()->subDay()->toDateString()]);

    $this->actingAs($w['unitAdmin'])->post(route('approvals.approve', $request))->assertRedirect()->assertSessionHas('error');

    expect($request->fresh()->status)->toBe('pending')->and($w['vehicles'][0]->fresh()->assigned_driver_id)->toBeNull();
});

it('holds an edit that changes the driver but applies harmless edits at once', function () {
    $w = apvWorld();
    $vehicle = $w['vehicles'][0];

    $this->actingAs($w['unitAdmin'])
        ->putJson("/vehicles/{$vehicle->id}", apvEditPayload($vehicle, ['color' => 'Black']))
        ->assertOk()->assertJsonMissing(['pending' => true]);
    expect($vehicle->fresh()->color)->toBe('Black')->and(ChangeRequest::count())->toBe(0);

    $this->actingAs($w['unitAdmin'])
        ->putJson("/vehicles/{$vehicle->id}", apvEditPayload($vehicle, ['assigned_driver_id' => $w['driver']->id, 'color' => 'Blue']))
        ->assertOk()->assertJson(['pending' => true]);
    expect($vehicle->fresh()->assigned_driver_id)->toBeNull()->and($vehicle->fresh()->color)->toBe('Black');

    // the Administrator approves → the whole edit is applied
    $this->actingAs($w['admin'])->post(route('approvals.approve', ChangeRequest::firstOrFail()))->assertRedirect()->assertSessionHas('success');
    expect($vehicle->fresh()->assigned_driver_id)->toBe($w['driver']->id)->and($vehicle->fresh()->color)->toBe('Blue');
});

it('holds disposal and deletion, and applies them once approved', function () {
    $w = apvWorld();
    [$a, $b] = $w['vehicles'];

    $this->actingAs($w['unitAdmin'])
        ->putJson("/vehicles/{$a->id}", apvEditPayload($a, ['status' => 'BER', 'ber_sub_status' => 'DISPOSED', 'disposal_date' => now()->toDateString()]))
        ->assertOk()->assertJson(['pending' => true]);
    expect($a->fresh()->isDisposed())->toBeFalse();

    $disposal = ChangeRequest::firstOrFail();
    expect($disposal->category)->toBe('disposal');
    $this->actingAs($w['admin'])->post(route('approvals.approve', $disposal))->assertRedirect();
    expect($a->fresh()->isDisposed())->toBeTrue();

    // deletion by an Administrator is held for the Super Administrator
    $this->actingAs($w['admin'])->delete("/vehicles/{$b->id}")->assertRedirect();
    expect(Vehicle::whereKey($b->id)->exists())->toBeTrue();
    $deletion = ChangeRequest::latest('id')->first();
    $this->actingAs($w['unitAdmin'])->post(route('approvals.approve', $deletion))->assertForbidden();
    $this->actingAs($w['super'])->post(route('approvals.approve', $deletion))->assertRedirect();
    expect(Vehicle::whereKey($b->id)->exists())->toBeFalse();
});

it('shows each approver only the requests they may decide', function () {
    $w = apvWorld();
    apvAssign($this, $w['stationAdmin'], $w['vehicles'][0], $w['driver'])->assertOk();

    $this->actingAs($w['unitAdmin'])->get('/approvals')->assertOk()->assertSee('Assign Juan Cruz to [ALPHA 1234]');
    $this->actingAs($w['otherUnitAdmin'])->get('/approvals')->assertOk()->assertDontSee('Waiting for your approval <span class="badge badge-warning">1</span>', false);
    $this->actingAs($w['stationAdmin'])->get('/approvals')->assertOk()->assertSee('My requests');
    $this->actingAs(apvUser('VIEWER'))->get('/approvals')->assertForbidden();
});
