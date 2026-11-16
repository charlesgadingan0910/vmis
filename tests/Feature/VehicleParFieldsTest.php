<?php

use App\Models\Driver;
use App\Models\Station;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\SimpleXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function parAdmin(): User
{
    return User::create([
        'account_type'        => 'SUPER ADMINISTRATOR',
        'lastname'            => 'Tester',
        'firstname'           => 'Par',
        'fullname'            => 'Par Tester',
        'badge_number'        => 'PAR-1',
        'email'               => 'par1@example.test',
        'password'            => 'secret-password',
        'is_active'           => '1',
        'is_password_changed' => '1',
    ]);
}

/** @return array{unit: Unit, station: Station, type: VehicleType, d1: Driver, d2: Driver} */
function parSetup(): array
{
    $unit = Unit::create(['unit_name' => 'Alpha Unit', 'unit_abbvr' => 'ALPHA']);

    return [
        'unit'    => $unit,
        'station' => Station::create(['unit_id' => $unit->id, 'station_name' => 'Alpha Station', 'station_abbvr' => 'ALS']),
        'type'    => VehicleType::create(['name' => 'Patrol Car']),
        'd1'      => Driver::create(['firstname' => 'Juan', 'lastname' => 'Dela Cruz', 'status' => 'active']),
        'd2'      => Driver::create(['firstname' => 'Maria', 'lastname' => 'Santos', 'status' => 'active']),
    ];
}

function parImportFile(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'par_') . '.xlsx';
    $headers = ['Plate Number', 'Make', 'Model', 'Variant', 'Vehicle Type', 'Unit', 'Station', 'Marked/Unmarked', 'Primary PAR Driver', 'Alternate PAR Driver'];
    SimpleXlsx::write($path, ['Vehicles' => array_merge([$headers], $rows)]);

    return new UploadedFile($path, 'vehicles.xlsx', null, null, true);
}

it('imports variant, marked status and PAR drivers', function () {
    $f = parSetup();

    $response = $this->actingAs(parAdmin())->post(route('vehicles.import'), ['file' => parImportFile([
        ['PAR 111', 'Toyota', 'Hilux', '4x2 mt', 'Patrol Car', 'Alpha Unit', 'Alpha Station', 'marked', 'Juan Dela Cruz', 'Santos, Maria'],
        ['PAR 222', 'Toyota', 'Vios', '', 'Patrol Car', 'Alpha Unit', 'Alpha Station', '', '', ''],
    ])])->assertOk();

    expect($response->json('imported'))->toBe(2);

    $one = Vehicle::where('plate_number', 'PAR 111')->first();
    expect($one->variant)->toBe('4X2 MT')
        ->and($one->marked_status)->toBe('MARKED')
        ->and($one->par_driver_id)->toBe($f['d1']->id)
        ->and($one->par_driver_id_2)->toBe($f['d2']->id)
        ->and($one->parDriver->id)->toBe($f['d1']->id);

    $two = Vehicle::where('plate_number', 'PAR 222')->first();
    expect($two->marked_status)->toBeNull()->and($two->par_driver_id)->toBeNull();
});

it('flags a bad marked status and identical PAR holders, but keeps unknown names as typed text', function () {
    parSetup();

    $response = $this->actingAs(parAdmin())->post(route('vehicles.import'), ['file' => parImportFile([
        ['BAD 111', 'Toyota', 'Hilux', '', 'Patrol Car', 'Alpha Unit', 'Alpha Station', 'camouflaged', '', ''],
        ['TYP 222', 'Toyota', 'Hilux', '', 'Patrol Car', 'Alpha Unit', 'Alpha Station', '', 'Nobody Here', ''],
        ['BAD 333', 'Toyota', 'Hilux', '', 'Patrol Car', 'Alpha Unit', 'Alpha Station', '', 'Juan Dela Cruz', 'juan dela cruz'],
        ['BAD 444', 'Toyota', 'Hilux', '', 'Patrol Car', 'Alpha Unit', 'Alpha Station', '', 'Foo Bar', 'foo  BAR'],
    ])])->assertOk();

    expect($response->json('imported'))->toBe(1)->and($response->json('errors'))->toBe(3);

    $typed = Vehicle::where('plate_number', 'TYP 222')->first();
    expect($typed->par_driver_id)->toBeNull()
        ->and($typed->par_driver_name)->toBe('Nobody Here')
        ->and($typed->parHolderName(1))->toBe('Nobody Here');
    expect(Vehicle::count())->toBe(1);
});

it('saves the new fields when registering and editing a vehicle', function () {
    $f = parSetup();
    $admin = parAdmin();

    $this->actingAs($admin)->post(route('vehicles.store'), [
        'plate_number'    => 'WEB 123', 'make' => 'Toyota', 'model' => 'Hilux', 'variant' => 'gl 4x4',
        'vehicle_type_id' => $f['type']->id, 'unit_id' => $f['unit']->id, 'station_id' => $f['station']->id,
        'source' => 'ORGANIC', 'status' => 'SERVICEABLE', 'marked_status' => 'UNMARKED',
        'par_driver_id' => $f['d1']->id, 'par_driver_id_2' => $f['d2']->id,
    ])->assertRedirect();

    $vehicle = Vehicle::where('plate_number', 'WEB 123')->first();
    expect($vehicle)->not->toBeNull()
        ->and($vehicle->variant)->toBe('GL 4X4')
        ->and($vehicle->marked_status)->toBe('UNMARKED')
        ->and($vehicle->par_driver_id)->toBe($f['d1']->id)
        ->and($vehicle->par_driver_id_2)->toBe($f['d2']->id);

    $this->actingAs($admin)->getJson(route('vehicles.edit-data', $vehicle))
        ->assertOk()
        ->assertJsonPath('marked_status', 'UNMARKED')
        ->assertJsonPath('variant', 'GL 4X4')
        ->assertJsonPath('par_driver_id', $f['d1']->id);
});

it('rejects the same driver as both PAR drivers', function () {
    $f = parSetup();

    $this->actingAs(parAdmin())->post(route('vehicles.store'), [
        'plate_number'    => 'WEB 999', 'make' => 'Toyota', 'model' => 'Hilux',
        'vehicle_type_id' => $f['type']->id, 'unit_id' => $f['unit']->id, 'station_id' => $f['station']->id,
        'source' => 'ORGANIC', 'status' => 'SERVICEABLE',
        'par_driver_id' => $f['d1']->id, 'par_driver_id_2' => $f['d1']->id,
    ])->assertSessionHasErrors('par_driver_id_2');

    expect(Vehicle::count())->toBe(0);
});

it('saves a typed PAR name, and a chosen driver replaces the typed name', function () {
    $f = parSetup();
    $admin = parAdmin();

    $base = [
        'make' => 'Toyota', 'model' => 'Hilux', 'vehicle_type_id' => $f['type']->id,
        'unit_id' => $f['unit']->id, 'station_id' => $f['station']->id, 'source' => 'ORGANIC', 'status' => 'SERVICEABLE',
    ];

    $this->actingAs($admin)->post(route('vehicles.store'), $base + [
        'plate_number' => 'TYP 111', 'par_driver_name' => '  Pedro   Reyes ', 'par_driver_name_2' => 'Ana Lopez',
    ])->assertRedirect();
    $typed = Vehicle::where('plate_number', 'TYP 111')->first();
    expect($typed->par_driver_name)->toBe('Pedro Reyes')
        ->and($typed->par_driver_name_2)->toBe('Ana Lopez')
        ->and($typed->par_driver_id)->toBeNull();

    $this->actingAs($admin)->post(route('vehicles.store'), $base + [
        'plate_number' => 'TYP 222', 'par_driver_id' => $f['d1']->id, 'par_driver_name' => 'Should Be Cleared',
    ])->assertRedirect();
    $linked = Vehicle::where('plate_number', 'TYP 222')->first();
    expect($linked->par_driver_id)->toBe($f['d1']->id)->and($linked->par_driver_name)->toBeNull();

    $this->actingAs($admin)->post(route('vehicles.store'), $base + [
        'plate_number' => 'TYP 333', 'par_driver_name' => 'Same Person', 'par_driver_name_2' => 'same person',
    ])->assertSessionHasErrors('par_driver_name_2');
});
