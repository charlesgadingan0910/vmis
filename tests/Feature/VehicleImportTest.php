<?php

use App\Models\Station;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\SimpleXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function importUser(string $accountType, array $attributes = []): User
{
    static $count = 0;
    $count++;

    return User::create(array_merge([
        'account_type'        => $accountType,
        'lastname'            => 'Tester',
        'firstname'           => 'Import',
        'fullname'            => 'Import Tester',
        'badge_number'        => 'IMP-' . $count,
        'email'               => "import{$count}@example.test",
        'password'            => 'secret-password',
        'is_active'           => '1',
        'is_password_changed' => '1',
    ], $attributes));
}

/** @return array{units: array<int, Unit>, stations: array<int, Station>, type: VehicleType} */
function importFleet(): array
{
    $type = VehicleType::create(['name' => 'Patrol Car']);
    $units = [];
    $stations = [];
    foreach (['Alpha', 'Bravo'] as $name) {
        $unit = Unit::create(['unit_name' => $name . ' Unit', 'unit_abbvr' => strtoupper($name)]);
        $stations[] = Station::create(['unit_id' => $unit->id, 'station_name' => $name . ' Station', 'station_abbvr' => strtoupper($name) . 'S']);
        $units[] = $unit;
    }

    return compact('units', 'stations', 'type');
}

const IMPORT_HEADERS = ['Plate Number', 'Make', 'Model', 'Vehicle Type', 'Unit', 'Station', 'Source', 'Status', 'Year Model'];

function importFile(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'imp_') . '.xlsx';
    SimpleXlsx::write($path, ['Vehicles' => array_merge([IMPORT_HEADERS], $rows)]);

    return new UploadedFile($path, 'vehicles.xlsx', null, null, true);
}

it('reads back what it writes', function () {
    $path = tempnam(sys_get_temp_dir(), 'rt_') . '.xlsx';
    SimpleXlsx::write($path, ['S' => [['A', 'B'], ['x & <y>', 'é']]]);

    expect(SimpleXlsx::read($path))->toBe([0 => ['A', 'B'], 1 => ['x & <y>', 'é']]);
});

it('imports valid rows, skips duplicates and reports bad rows', function () {
    $fleet = importFleet();
    $admin = importUser('SUPER ADMINISTRATOR');
    Vehicle::create([
        'plate_number' => 'DUP 111', 'make' => 'TOYOTA', 'model' => 'VIOS', 'vehicle_type_id' => $fleet['type']->id,
        'unit_id' => $fleet['units'][0]->id, 'station_id' => $fleet['stations'][0]->id, 'status' => 'SERVICEABLE',
    ]);

    $file = importFile([
        ['abc 123', 'toyota', 'hilux', 'patrol car', 'Alpha Unit', 'Alpha Station', 'organic', 'serviceable', '2022'],
        ['DUP 111', 'Toyota', 'Vios', 'Patrol Car', 'Alpha Unit', 'Alpha Station', '', '', ''],
        ['BAD 999', 'Toyota', 'Vios', 'Spaceship', 'Alpha Unit', 'Alpha Station', '', '', ''],
        ['ABC 123', 'Toyota', 'Vios', 'Patrol Car', 'Alpha Unit', 'Alpha Station', '', '', ''],
        ['NEW 777', 'Isuzu', 'D-Max', 'Patrol Car', '', 'Bravo Station', '', 'BER', ''],
    ]);

    $response = $this->actingAs($admin)->post(route('vehicles.import'), ['file' => $file])->assertOk();

    expect($response->json('imported'))->toBe(2)
        ->and($response->json('duplicates'))->toBe(1)
        ->and($response->json('errors'))->toBe(2);

    $first = Vehicle::where('plate_number', 'ABC 123')->first();
    expect($first)->not->toBeNull()
        ->and($first->make)->toBe('TOYOTA')
        ->and($first->source)->toBe('ORGANIC')
        ->and($first->qr_code)->not->toBeEmpty()
        ->and($first->encoded_by)->toBe($admin->id);

    $ber = Vehicle::where('plate_number', 'NEW 777')->first();
    expect($ber->unit_id)->toBe($fleet['units'][1]->id)
        ->and($ber->ber_sub_status)->toBe('FOR_DISPOSAL');

    expect(Vehicle::where('plate_number', 'BAD 999')->exists())->toBeFalse();
});

it('saves nothing in preview mode', function () {
    $fleet = importFleet();
    $admin = importUser('ADMINISTRATOR');

    $response = $this->actingAs($admin)->post(route('vehicles.import'), [
        'preview' => 1,
        'file'    => importFile([['PRE 123', 'Toyota', 'Hilux', 'Patrol Car', 'Alpha Unit', 'Alpha Station', '', '', '']]),
    ])->assertOk();

    expect($response->json('ready'))->toBe(1)->and($response->json('imported'))->toBe(0);
    expect(Vehicle::count())->toBe(0);
});

it('forces a unit administrator into their own unit', function () {
    $fleet = importFleet();
    $unitAdmin = importUser('UNIT ADMINISTRATOR', ['unit_id' => $fleet['units'][0]->id]);

    $response = $this->actingAs($unitAdmin)->post(route('vehicles.import'), [
        'file' => importFile([
            ['OWN 111', 'Toyota', 'Hilux', 'Patrol Car', '', 'Alpha Station', '', '', ''],
            ['OTH 222', 'Toyota', 'Hilux', 'Patrol Car', '', 'Bravo Station', '', '', ''],
        ]),
    ])->assertOk();

    expect($response->json('imported'))->toBe(1)->and($response->json('errors'))->toBe(1);
    expect(Vehicle::where('plate_number', 'OWN 111')->first()->unit_id)->toBe($fleet['units'][0]->id);
    expect(Vehicle::where('plate_number', 'OTH 222')->exists())->toBeFalse();
});

it('forces a station administrator into their own station', function () {
    $fleet = importFleet();
    $stationAdmin = importUser('STATION ADMINISTRATOR', [
        'unit_id'    => $fleet['units'][1]->id,
        'station_id' => $fleet['stations'][1]->id,
    ]);

    $this->actingAs($stationAdmin)->post(route('vehicles.import'), [
        'file' => importFile([['STN 111', 'Toyota', 'Hilux', 'Patrol Car', '', '', '', '', '']]),
    ])->assertOk()->assertJsonPath('imported', 1);

    $vehicle = Vehicle::where('plate_number', 'STN 111')->first();
    expect($vehicle->station_id)->toBe($fleet['stations'][1]->id)
        ->and($vehicle->unit_id)->toBe($fleet['units'][1]->id);
});

it('rejects files missing required columns', function () {
    importFleet();
    $admin = importUser('SUPER ADMINISTRATOR');
    $path = tempnam(sys_get_temp_dir(), 'bad_') . '.xlsx';
    SimpleXlsx::write($path, ['S' => [['Plate Number', 'Make'], ['AAA 111', 'Toyota']]]);

    $this->actingAs($admin)
        ->post(route('vehicles.import'), ['file' => new UploadedFile($path, 'bad.xlsx', null, null, true)])
        ->assertStatus(422);
});

it('blocks viewers and serves the template to admins', function () {
    importFleet();

    $this->actingAs(importUser('VIEWER'))->get(route('vehicles.import.template'))->assertForbidden();
    $this->actingAs(importUser('VIEWER'))->post(route('vehicles.import'), ['file' => importFile([])])->assertForbidden();
    $this->actingAs(importUser('SUPER ADMINISTRATOR'))->get(route('vehicles.import.template'))->assertOk();
});
