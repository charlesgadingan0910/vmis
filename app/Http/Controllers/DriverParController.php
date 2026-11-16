<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Services\ApprovalService;
use App\Models\DriverAssignment;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * PAR (Property Acknowledgement Receipt) vehicles of a driver, managed from
 * Driver Management.
 *
 * The Primary / Alternate PAR drivers of a vehicle ARE its Assigned Driver 1 / 2
 * (assigned_driver_id / assigned_driver_id_2). A PAR holder who is not a driver yet
 * is kept on the vehicle as typed text (par_driver_name / par_driver_name_2). This
 * lists the vehicles a driver already holds the PAR for, plus the typed names not
 * yet linked to anyone — names matching this driver come first — and lets an admin
 * link a typed name to the driver. Linking assigns the driver to that slot exactly
 * like the Register/Edit Vehicle form does (including the assignment-history row and
 * the expired-license guard).
 *
 * Scoping mirrors the rest of the app: Unit/Station administrators only see and change
 * their own vehicles, Viewers are read-only, Driver accounts are blocked.
 */
class DriverParController extends Controller
{
    protected const MAX_UNLINKED = 300;

    public function index(Driver $driver): JsonResponse
    {
        $user = $this->actor();

        $linked = [];
        $linkedVehicles = $this->visibleVehicles($user)
            ->where(fn ($q) => $q->where('assigned_driver_id', $driver->id)->orWhere('assigned_driver_id_2', $driver->id))
            ->orderBy('plate_number')->get();
        foreach ($linkedVehicles as $vehicle) {
            foreach ([1 => 'assigned_driver_id', 2 => 'assigned_driver_id_2'] as $slot => $column) {
                if ((int) $vehicle->{$column} === $driver->id) {
                    $linked[] = $this->row($vehicle, $slot, $vehicle->parHolderName($slot));
                }
            }
        }

        $keys = $driver->nameKeys();
        $unlinked = [];
        $typedVehicles = $this->visibleVehicles($user)
            ->where(fn ($q) => $q->where(fn ($a) => $a->whereNull('assigned_driver_id')->whereNotNull('par_driver_name')->where('par_driver_name', '!=', ''))
                ->orWhere(fn ($a) => $a->whereNull('assigned_driver_id_2')->whereNotNull('par_driver_name_2')->where('par_driver_name_2', '!=', '')))
            ->orderBy('plate_number')->get();
        foreach ($typedVehicles as $vehicle) {
            foreach ([1 => ['assigned_driver_id', 'par_driver_name'], 2 => ['assigned_driver_id_2', 'par_driver_name_2']] as $slot => [$idColumn, $nameColumn]) {
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
        [$idColumn, $nameColumn] = $slot === 2 ? ['assigned_driver_id_2', 'par_driver_name_2'] : ['assigned_driver_id', 'par_driver_name'];
        $otherColumn = $slot === 2 ? 'assigned_driver_id' : 'assigned_driver_id_2';
        $driverName = trim($driver->firstname . ' ' . $driver->lastname);

        if ($vehicle->{$idColumn} !== null || trim((string) $vehicle->{$nameColumn}) === '') {
            return response()->json(['message' => 'That PAR slot is not an unlinked typed name any more. Refresh the list.'], 422);
        }

        // Same guard the Register/Edit Vehicle form applies before assigning a driver.
        if ($driver->isLicenseExpired()) {
            return response()->json(['message' => "Cannot link {$driverName} — their Driver's License expired on " . $driver->license_expiration_date->format('M d, Y') . '. Update the license record first.'], 422);
        }

        // The same person can't hold both slots.
        $otherHolder = $vehicle->parHolderName($slot === 1 ? 2 : 1);
        $otherKeys = $otherHolder ? [Str::lower($otherHolder)] : [];
        if ((int) $vehicle->{$otherColumn} === $driver->id || array_intersect($driver->nameKeys(), $otherKeys)) {
            return response()->json(['message' => 'This driver already holds the other PAR slot on that vehicle.'], 422);
        }

        if ($held = app(ApprovalService::class)->hold(
            'par.link', 'assignment', $vehicle, $driver,
            'Link ' . ($slot === 1 ? 'Primary' : 'Alternate') . ' PAR driver "' . $vehicle->{$nameColumn} . '" to ' . $driverName . ' on [' . strtoupper($vehicle->plate_number) . ']',
            $data
        )) {
            return $held;
        }

        $typed = $vehicle->{$nameColumn};
        $vehicle->forceFill([$idColumn => $driver->id, $nameColumn => null])->save();

        DriverAssignment::create([
            'vehicle_id'  => $vehicle->id,
            'driver_id'   => $driver->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'notes'       => 'Linked from typed PAR name "' . $typed . '".',
        ]);

        ActivityLog::record(
            'updated',
            'Driver Assignment',
            'Linked ' . ($slot === 1 ? 'Primary' : 'Alternate') . ' PAR Driver "' . $typed . '" to ' . $driverName . ' on [' . strtoupper($vehicle->plate_number) . '].',
            $vehicle,
            ['before' => [$nameColumn => $typed], 'after' => [$idColumn => $driver->id]]
        );

        return response()->json(['success' => true, 'message' => 'Linked ' . strtoupper($vehicle->plate_number) . ' to ' . $driverName . '.']);
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

    protected function visibleVehicles($user)
    {
        $query = Vehicle::query()->with(['driver', 'driver2']);
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
