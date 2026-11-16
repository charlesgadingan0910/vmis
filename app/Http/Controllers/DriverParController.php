<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * PAR (Property Acknowledgement Receipt) vehicles of a driver, managed from
 * Driver Management.
 *
 * A vehicle's PAR holder can be recorded as typed text (par_driver_name /
 * par_driver_name_2) before the person exists as a driver. Here an admin sees
 * the vehicles already linked to a driver, plus typed names — those matching
 * this driver's name are suggested first — and can link a typed name to the
 * driver (or turn a link back into typed text). Scoping mirrors the rest of
 * the app: Unit/Station administrators only see and change their own vehicles,
 * Viewers are read-only, Driver accounts are blocked.
 */
class DriverParController extends Controller
{
    protected const MAX_UNLINKED = 300;

    public function index(Driver $driver): JsonResponse
    {
        $user = $this->actor();

        $linked = [];
        $linkedVehicles = $this->visibleVehicles($user)
            ->where(fn ($q) => $q->where('par_driver_id', $driver->id)->orWhere('par_driver_id_2', $driver->id))
            ->orderBy('plate_number')->get();
        foreach ($linkedVehicles as $vehicle) {
            foreach ([1 => 'par_driver_id', 2 => 'par_driver_id_2'] as $slot => $column) {
                if ((int) $vehicle->{$column} === $driver->id) {
                    $linked[] = $this->row($vehicle, $slot, $vehicle->parHolderName($slot));
                }
            }
        }

        $keys = $this->nameKeys($driver);
        $unlinked = [];
        $typedVehicles = $this->visibleVehicles($user)
            ->where(fn ($q) => $q->where(fn ($a) => $a->whereNull('par_driver_id')->whereNotNull('par_driver_name')->where('par_driver_name', '!=', ''))
                ->orWhere(fn ($a) => $a->whereNull('par_driver_id_2')->whereNotNull('par_driver_name_2')->where('par_driver_name_2', '!=', '')))
            ->orderBy('plate_number')->get();
        foreach ($typedVehicles as $vehicle) {
            foreach ([1 => ['par_driver_id', 'par_driver_name'], 2 => ['par_driver_id_2', 'par_driver_name_2']] as $slot => [$idColumn, $nameColumn]) {
                $name = trim((string) $vehicle->{$nameColumn});
                if ($vehicle->{$idColumn} === null && $name !== '') {
                    $row = $this->row($vehicle, $slot, $name);
                    $row['suggested'] = in_array(Str::lower(preg_replace('/\s+/', ' ', $name)), $keys, true);
                    $unlinked[] = $row;
                }
            }
        }
        usort($unlinked, fn ($a, $b) => [$b['suggested'], $a['plate']] <=> [$a['suggested'], $b['plate']]);

        return response()->json([
            'linked'    => $linked,
            'unlinked'  => array_slice($unlinked, 0, self::MAX_UNLINKED),
            'truncated' => count($unlinked) > self::MAX_UNLINKED,
            'can_link'  => $this->role($user) !== 'VIEWER',
        ]);
    }

    public function link(Request $request, Driver $driver): JsonResponse
    {
        $user = $this->actor(write: true);
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'slot'       => ['required', 'in:1,2'],
        ]);

        $vehicle = $this->visibleVehicles($user)->findOrFail($data['vehicle_id']);
        $slot = (int) $data['slot'];
        [$idColumn, $nameColumn] = $this->columns($slot);
        [$otherId, $otherName] = $this->columns($slot === 1 ? 2 : 1);

        if ($vehicle->{$idColumn} !== null || trim((string) $vehicle->{$nameColumn}) === '') {
            return response()->json(['message' => 'That PAR slot is not an unlinked typed name any more. Refresh the list.'], 422);
        }

        // The same person can't hold both slots.
        $otherHolder = Str::lower((string) $vehicle->parHolderName($slot === 1 ? 2 : 1));
        $driverName = Str::lower(trim($driver->firstname . ' ' . $driver->lastname));
        if ((int) $vehicle->{$otherId} === $driver->id || ($otherHolder !== '' && $otherHolder === $driverName)) {
            return response()->json(['message' => 'This driver already holds the other PAR slot on that vehicle.'], 422);
        }

        $typed = $vehicle->{$nameColumn};
        $vehicle->forceFill([$idColumn => $driver->id, $nameColumn => null])->save();

        ActivityLog::record(
            'updated',
            'Vehicle',
            'Linked ' . ($slot === 1 ? 'Primary' : 'Alternate') . ' PAR Driver "' . $typed . '" to driver ' . trim($driver->firstname . ' ' . $driver->lastname) . ' on [' . strtoupper($vehicle->plate_number) . '].',
            $vehicle,
            ['before' => [$nameColumn => $typed], 'after' => [$idColumn => $driver->id]]
        );

        return response()->json(['success' => true, 'message' => 'Linked ' . strtoupper($vehicle->plate_number) . ' to ' . trim($driver->firstname . ' ' . $driver->lastname) . '.']);
    }

    public function unlink(Request $request, Driver $driver): JsonResponse
    {
        $user = $this->actor(write: true);
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'slot'       => ['required', 'in:1,2'],
        ]);

        $vehicle = $this->visibleVehicles($user)->findOrFail($data['vehicle_id']);
        $slot = (int) $data['slot'];
        [$idColumn, $nameColumn] = $this->columns($slot);

        if ((int) $vehicle->{$idColumn} !== $driver->id) {
            return response()->json(['message' => 'That vehicle is not linked to this driver in that slot.'], 422);
        }

        // Keep the person's name on the vehicle as typed text so the PAR holder isn't lost.
        $name = trim($driver->firstname . ' ' . $driver->lastname);
        $vehicle->forceFill([$idColumn => null, $nameColumn => $name])->save();

        ActivityLog::record(
            'updated',
            'Vehicle',
            'Unlinked ' . ($slot === 1 ? 'Primary' : 'Alternate') . ' PAR Driver ' . $name . ' on [' . strtoupper($vehicle->plate_number) . '] (kept as typed name).',
            $vehicle,
            ['before' => [$idColumn => $driver->id], 'after' => [$nameColumn => $name]]
        );

        return response()->json(['success' => true, 'message' => 'Unlinked. The name is kept on ' . strtoupper($vehicle->plate_number) . ' as typed text.']);
    }

    /** @return array<string, mixed> */
    protected function row(Vehicle $vehicle, int $slot, ?string $name): array
    {
        return [
            'vehicle_id' => $vehicle->id,
            'slot'       => $slot,
            'slot_label' => $slot === 1 ? 'Primary' : 'Alternate',
            'plate'      => strtoupper($vehicle->plate_number),
            'label'      => trim($vehicle->make . ' ' . $vehicle->model),
            'name'       => $name,
        ];
    }

    /** @return array{0: string, 1: string} */
    protected function columns(int $slot): array
    {
        return $slot === 2 ? ['par_driver_id_2', 'par_driver_name_2'] : ['par_driver_id', 'par_driver_name'];
    }

    /** Lower-cased spellings of this driver's name that count as a match. @return array<int, string> */
    protected function nameKeys(Driver $driver): array
    {
        $first = trim($driver->firstname);
        $last = trim($driver->lastname);

        return array_values(array_unique(array_map(
            fn ($v) => Str::lower(preg_replace('/\s+/', ' ', $v)),
            [$first . ' ' . $last, $last . ', ' . $first, $last . ' ' . $first]
        )));
    }

    protected function visibleVehicles($user)
    {
        $query = Vehicle::query()->with(['parDriver', 'parDriver2']);
        $role = $this->role($user);

        if ($role === 'UNIT ADMINISTRATOR') {
            $query->where('unit_id', $user->unit_id);
        } elseif ($role === 'STATION ADMINISTRATOR') {
            $query->where('station_id', $user->station_id);
        }

        return $query;
    }

    protected function actor(bool $write = false)
    {
        $user = auth()->user();
        $role = $this->role($user);

        abort_if($role === 'DRIVER', 403, 'Unauthorized Action');
        abort_if($write && $role === 'VIEWER', 403, 'Viewer accounts have read-only access.');

        return $user;
    }

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }
}
