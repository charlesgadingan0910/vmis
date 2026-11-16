<?php

use App\Models\Driver;
use App\Models\Station;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function parLinkUser(string $accountType, array $attributes = []): User
{
    static $count = 0;
    $count++;

    return User::create(array_merge([
        'account_type'        => $accountType,
        'lastname'            => 'Tester',
        'firstname'           => 'Link',
        'fullname'            => 'Link Tester',
        'badge_number'        => 'PLK-' . $count,
        'email'               => "parlink{$count}@example.test",
        'password'            => 'secret-password',
        'is_active'           => '1',
        'is_password_changed' => '1',
    ], $attributes));
}

/** @return array{driver: Driver, units: array<int, Unit>, a: Vehicle, b: Vehicle, c: Vehicle} */
function parLinkFleet(): array
{
    $type = VehicleType::create(['name' => 'Patrol Car']);
    $units = [];
    $stations = [];
    foreach (['Alpha', 'Bravo'] as $name) {
        $units[] = $unit = Unit::create(['unit_name' => $name . ' Unit', 'unit_abbvr' => strtoupper($name)]);
        $stations[] = Station::create(['unit_id' => $unit->id, 'station_name' => $name . ' Station', 'station_abbvr' => strtoupper($name) . 'S']);
    }

    $make = fn (string $plate, int $i, array $extra = []) => Vehicle::create(array_merge([
        'plate_number' => $plate, 'make' => 'TOYOTA', 'model' => 'HILUX', 'vehicle_type_id' => $type->id,
        'unit_id' => $units[$i]->id, 'station_id' => $stations[$i]->id, 'status' => 'SERVICEABLE',
    ], $extra));

    return [
        'driver' => Driver::create(['firstname' => 'Juan', 'lastname' => 'Dela Cruz', 'status' => 'active']),
        'units'  => $units,
        'a'      => $make('AAA 111', 0, ['par_driver_name' => 'juan dela cruz']),
        'b'      => $make('BBB 222', 1, ['par_driver_name_2' => 'Someone Else']),
        'c'      => $make('CCC 333', 0),
    ];
}

it('lists typed PAR names with matching ones suggested first', function () {
    $f = parLinkFleet();

    $response = $this->actingAs(parLinkUser('SUPER ADMINISTRATOR'))
        ->getJson(route('drivers.par-vehicles', $f['driver']))->assertOk();

    expect($response->json('linked'))->toBe([])
        ->and($response->json('unlinked'))->toHaveCount(2)
        ->and($response->json('unlinked.0.plate'))->toBe('AAA 111')
        ->and($response->json('unlinked.0.suggested'))->toBeTrue()
        ->and($response->json('unlinked.1.suggested'))->toBeFalse();
});

it('links a typed PAR name to a driver and can unlink it again', function () {
    $f = parLinkFleet();
    $admin = parLinkUser('SUPER ADMINISTRATOR');

    $this->actingAs($admin)->postJson(route('drivers.par-link', $f['driver']), ['vehicle_id' => $f['a']->id, 'slot' => 1])
        ->assertOk()->assertJsonPath('success', true);

    $f['a']->refresh();
    expect($f['a']->par_driver_id)->toBe($f['driver']->id)->and($f['a']->par_driver_name)->toBeNull();

    $linked = $this->actingAs($admin)->getJson(route('drivers.par-vehicles', $f['driver']))->json('linked');
    expect($linked)->toHaveCount(1)->and($linked[0]['plate'])->toBe('AAA 111');

    // Already linked: linking again is refused.
    $this->actingAs($admin)->postJson(route('drivers.par-link', $f['driver']), ['vehicle_id' => $f['a']->id, 'slot' => 1])->assertStatus(422);

    $this->actingAs($admin)->postJson(route('drivers.par-unlink', $f['driver']), ['vehicle_id' => $f['a']->id, 'slot' => 1])->assertOk();
    $f['a']->refresh();
    expect($f['a']->par_driver_id)->toBeNull()->and($f['a']->par_driver_name)->toBe('Juan Dela Cruz');
});

it('refuses to link the same driver to both PAR slots of one vehicle', function () {
    $f = parLinkFleet();
    $f['a']->update(['par_driver_id' => $f['driver']->id, 'par_driver_name' => null, 'par_driver_name_2' => 'Juan Dela Cruz']);

    $this->actingAs(parLinkUser('SUPER ADMINISTRATOR'))
        ->postJson(route('drivers.par-link', $f['driver']), ['vehicle_id' => $f['a']->id, 'slot' => 2])
        ->assertStatus(422);
});

it('keeps unit administrators inside their own unit and viewers read-only', function () {
    $f = parLinkFleet();
    $unitAdmin = parLinkUser('UNIT ADMINISTRATOR', ['unit_id' => $f['units'][0]->id]);

    // Vehicle B belongs to the other unit.
    $this->actingAs($unitAdmin)->postJson(route('drivers.par-link', $f['driver']), ['vehicle_id' => $f['b']->id, 'slot' => 2])->assertNotFound();
    $plates = collect($this->actingAs($unitAdmin)->getJson(route('drivers.par-vehicles', $f['driver']))->json('unlinked'))->pluck('plate');
    expect($plates->all())->toBe(['AAA 111']);

    $viewer = parLinkUser('VIEWER');
    $this->actingAs($viewer)->getJson(route('drivers.par-vehicles', $f['driver']))->assertOk()->assertJsonPath('can_link', false);
    $this->actingAs($viewer)->postJson(route('drivers.par-link', $f['driver']), ['vehicle_id' => $f['a']->id, 'slot' => 1])->assertForbidden();
});
