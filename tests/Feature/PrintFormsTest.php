<?php

use App\Models\MaintenanceRecord;
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
function printUser(string $accountType, array $attributes = []): User
{
    static $count = 0;
    $count++;

    return User::create(array_merge([
        'account_type'        => $accountType,
        'lastname'            => 'Tester',
        'firstname'           => 'Print',
        'fullname'            => 'Print Tester',
        'badge_number'        => 'PRT-' . $count,
        'email'               => "print{$count}@example.test",
        'password'            => 'secret-password',
        'is_active'           => '1',
        'is_password_changed' => '1',
    ], $attributes));
}

/**
 * Two units with one vehicle each; the first vehicle also gets a requested repair.
 *
 * @return array{units: array<int, Unit>, vehicles: array<int, Vehicle>, record: MaintenanceRecord}
 */
function printFleet(): array
{
    $type = VehicleType::create(['name' => 'Patrol Car']);
    $units = [];
    $vehicles = [];

    foreach (['Alpha', 'Bravo'] as $name) {
        $unit = Unit::create(['unit_name' => $name . ' Unit', 'unit_abbvr' => strtoupper($name)]);
        $station = Station::create(['unit_id' => $unit->id, 'station_name' => $name . ' Station', 'station_abbvr' => strtoupper($name)]);

        $vehicles[] = Vehicle::create([
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

    $record = MaintenanceRecord::create([
        'vehicle_id'        => $vehicles[0]->id,
        'maintenance_type'  => 'REPAIR',
        'nature_of_request' => 'REPAIR_OTHER',
        'stage'             => MaintenanceRecord::STAGE_REQUESTED,
        'request_date'      => '2026-10-01',
        'service_date'      => '2026-10-01',
        'requested_by'      => 'PCPT Requester',
        'description'       => 'Brake noise',
        'control_number'    => 'SR-000001',
    ]);

    return ['units' => $units, 'vehicles' => $vehicles, 'record' => $record];
}

it('prints the service request form with the record details', function () {
    $fleet = printFleet();

    $this->actingAs(printUser('ADMINISTRATOR'))
        ->get(route('print.maintenance', $fleet['record']))
        ->assertOk()
        ->assertSee('Motorpool Service Request Form')
        ->assertSee('SR-000001')
        ->assertSee('ALPHA-1234')
        ->assertDontSee('Technical Inspection Report');
});

it('prints a blank inspection form when it is asked for by name', function () {
    $fleet = printFleet();

    $this->actingAs(printUser('ADMINISTRATOR'))
        ->get(route('print.maintenance', ['maintenanceRecord' => $fleet['record'], 'form' => 'inspection']))
        ->assertOk()
        ->assertSee('Technical Inspection Report')
        ->assertDontSee('Motorpool Service Request Form');
});

it('rejects an unknown form name', function () {
    $fleet = printFleet();

    $this->actingAs(printUser('ADMINISTRATOR'))
        ->get(route('print.maintenance', ['maintenanceRecord' => $fleet['record'], 'form' => 'nope']))
        ->assertNotFound();
});

it('keeps a unit administrator from printing another unit\'s forms', function () {
    $fleet = printFleet();
    $bravoAdmin = printUser('UNIT ADMINISTRATOR', ['unit_id' => $fleet['units'][1]->id]);

    $this->actingAs($bravoAdmin)->get(route('print.maintenance', $fleet['record']))->assertForbidden();
    $this->actingAs($bravoAdmin)->get(route('vehicles.data-card', $fleet['vehicles'][0]))->assertForbidden();
});

it('blocks driver accounts from printing forms', function () {
    $fleet = printFleet();

    $this->actingAs(printUser('DRIVER'))->get(route('print.maintenance', $fleet['record']))->assertForbidden();
});

it('prints the vehicle data card and logs it', function () {
    $fleet = printFleet();

    $this->actingAs(printUser('VIEWER'))
        ->get(route('vehicles.data-card', $fleet['vehicles'][0]))
        ->assertOk()
        ->assertSee('Vehicle Data Card')
        ->assertSee('ALPHA-1234');

    $this->assertDatabaseHas('activity_logs', ['module' => 'Print Forms', 'action' => 'printed']);
});

it('prints QR stickers for every filtered vehicle within the user\'s scope', function () {
    $fleet = printFleet();

    $this->actingAs(printUser('ADMINISTRATOR'))
        ->get(route('vehicles.qr.print', ['filtered' => 1]))
        ->assertOk()
        ->assertSee('ALPHA-1234')
        ->assertSee('BRAVO-1234');

    // A unit filter cannot widen a unit administrator beyond their own unit.
    $this->actingAs(printUser('UNIT ADMINISTRATOR', ['unit_id' => $fleet['units'][0]->id]))
        ->get(route('vehicles.qr.print', ['filtered' => 1, 'unit_id' => $fleet['units'][1]->id]))
        ->assertOk()
        ->assertSee('ALPHA-1234')
        ->assertDontSee('BRAVO-1234');
});

it('applies the status filter when printing all filtered QR stickers', function () {
    $fleet = printFleet();
    $fleet['vehicles'][1]->update(['status' => 'UNSERVICEABLE']);

    $this->actingAs(printUser('ADMINISTRATOR'))
        ->get(route('vehicles.qr.print', ['filtered' => 1, 'status' => 'UNSERVICEABLE']))
        ->assertOk()
        ->assertSee('BRAVO-1234')
        ->assertDontSee('ALPHA-1234');
});
