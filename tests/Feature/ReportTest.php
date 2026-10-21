<?php

use App\Models\Station;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function reportUser(string $accountType, array $attributes = []): User
{
    static $count = 0;
    $count++;

    return User::create(array_merge([
        'account_type'        => $accountType,
        'lastname'            => 'Tester',
        'firstname'           => 'Report',
        'fullname'            => 'Report Tester',
        'badge_number'        => 'RPT-' . $count,
        'email'               => "report{$count}@example.test",
        'password'            => 'secret-password',
        'is_active'           => '1',
        'is_password_changed' => '1',
    ], $attributes));
}

/**
 * Two units, each with one station and one vehicle.
 *
 * @return array{0: Unit, 1: Unit}
 */
function reportFleet(): array
{
    $type = VehicleType::create(['name' => 'Patrol Car']);
    $units = [];

    foreach (['Alpha', 'Bravo'] as $name) {
        $unit = Unit::create(['unit_name' => $name . ' Unit', 'unit_abbvr' => strtoupper($name)]);
        $station = Station::create(['unit_id' => $unit->id, 'station_name' => $name . ' Station', 'station_abbvr' => strtoupper($name)]);

        Vehicle::create([
            'plate_number'    => strtoupper($name) . '-1234',
            'make'            => 'Toyota',
            'model'           => 'Hilux',
            'vehicle_type_id' => $type->id,
            'unit_id'         => $unit->id,
            'station_id'      => $station->id,
            'status'          => 'SERVICEABLE',
        ]);

        $units[] = $unit;
    }

    return $units;
}

it('blocks driver accounts from reports', function () {
    $driver = reportUser('DRIVER');

    $this->actingAs($driver)->get(route('reports.index'))->assertForbidden();
    $this->actingAs($driver)->get(route('reports.show', 'master-vehicles'))->assertForbidden();
});

it('lets a super administrator export every vehicle as csv', function () {
    reportFleet();

    $response = $this->actingAs(reportUser('SUPER ADMINISTRATOR'))
        ->get(route('reports.show', ['report' => 'master-vehicles', 'format' => 'csv']));

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('ALPHA-1234')->toContain('BRAVO-1234');
});

it('limits a unit administrator to their own unit', function () {
    [$alpha] = reportFleet();

    $response = $this->actingAs(reportUser('UNIT ADMINISTRATOR', ['unit_id' => $alpha->id]))
        ->get(route('reports.show', ['report' => 'master-vehicles', 'format' => 'csv']));

    $csv = $response->streamedContent();

    expect($csv)->toContain('ALPHA-1234')->not->toContain('BRAVO-1234');
});

it('does not let a unit filter widen a unit administrator beyond their unit', function () {
    [$alpha, $bravo] = reportFleet();

    $response = $this->actingAs(reportUser('UNIT ADMINISTRATOR', ['unit_id' => $alpha->id]))
        ->get(route('reports.show', ['report' => 'master-vehicles', 'format' => 'csv', 'unit_id' => $bravo->id]));

    expect($response->streamedContent())->not->toContain('BRAVO-1234')->not->toContain('ALPHA-1234');
});

it('rejects a date range that ends before it starts', function () {
    $this->actingAs(reportUser('ADMINISTRATOR'))
        ->get(route('reports.show', ['report' => 'fuel-summary', 'date_from' => '2026-10-10', 'date_to' => '2026-10-01']))
        ->assertStatus(422);
});

it('returns not found for an unknown report', function () {
    $this->actingAs(reportUser('ADMINISTRATOR'))
        ->get(route('reports.show', 'does-not-exist'))
        ->assertNotFound();
});

it('records each export in the activity log', function () {
    reportFleet();

    $this->actingAs(reportUser('VIEWER'))
        ->get(route('reports.show', ['report' => 'unit-recap']))
        ->assertOk();

    $this->assertDatabaseHas('activity_logs', ['module' => 'Reports', 'action' => 'exported']);
});
