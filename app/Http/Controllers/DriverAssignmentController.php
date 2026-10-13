<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Station;
use App\Models\Unit;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Driver Assignments — who currently has custody of which vehicle, and the
 * full history of past assignments (see the driver_assignments migration's
 * docblock for why that history is its own table rather than just reading
 * vehicles.assigned_driver_id, which only ever holds the current one).
 *
 * This writes the exact same vehicles.assigned_driver_id column the
 * Vehicle Inventory Edit form's "Assigned Driver" field does, so the
 * write-scoping here deliberately mirrors VehicleController's
 * authorizeVehicleWrite() precisely: VIEWER is blocked entirely, a UNIT/
 * STATION ADMINISTRATOR can only assign/unassign within their own unit/
 * station, SUPER ADMINISTRATOR and ADMINISTRATOR are unrestricted. DRIVER
 * accounts don't use this page at all — they see their own vehicle
 * assignment (if any) implicitly through what Trip Logs lets them log for.
 * VehicleController::store()/update() write to driver_assignments too
 * (see their inline hooks), so this table stays accurate no matter which
 * screen was used to change the assignment.
 */
class DriverAssignmentController extends Controller
{
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';
    protected const ROLE_DRIVER        = 'DRIVER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    protected function scopeToVisibleVehicles($query, $user, string $role): void
    {
        if ($role === self::ROLE_UNIT_ADMIN) {
            $query->where('unit_id', $user->unit_id);
        } elseif ($role === self::ROLE_STATION_ADMIN) {
            $query->where('station_id', $user->station_id);
        }
        // Broad-visibility roles (SUPER_ADMIN/ADMIN/VIEWER): no filter.
    }

    /**
     * Same shape as VehicleController::authorizeVehicleWrite() — VIEWER
     * blocked outright, UNIT/STATION admin confined to their own vehicles.
     */
    protected function authorizeWrite(Vehicle $vehicle, $user, string $role): void
    {
        if ($role === self::ROLE_VIEWER || $role === self::ROLE_DRIVER) {
            abort(403, 'Your account type cannot change vehicle assignments.');
        }
        if ($role === self::ROLE_UNIT_ADMIN && $vehicle->unit_id != $user->unit_id) {
            abort(403, 'Unauthorized Action');
        }
        if ($role === self::ROLE_STATION_ADMIN && $vehicle->station_id != $user->station_id) {
            abort(403, 'Unauthorized Action');
        }
    }

    protected function canView(Vehicle $vehicle, $user, string $role): bool
    {
        if (in_array($role, self::BROAD_VISIBILITY_ROLES, true)) {
            return true;
        }
        if ($role === self::ROLE_UNIT_ADMIN) {
            return $vehicle->unit_id == $user->unit_id;
        }
        if ($role === self::ROLE_STATION_ADMIN) {
            return $vehicle->station_id == $user->station_id;
        }
        return false;
    }

    /**
     * Same guard VehicleController::store()/update() use before letting a
     * driver be (re-)assigned — an expired license blocks the assignment.
     */
    protected function expiredLicenseAssignmentError($driverId): ?string
    {
        if (! $driverId) {
            return null;
        }

        $driver = Driver::find($driverId);
        if (! $driver || ! $driver->isLicenseExpired()) {
            return null;
        }

        return 'Cannot assign ' . trim($driver->firstname . ' ' . $driver->lastname) . " to this vehicle — their Driver's License expired on " . $driver->license_expiration_date->format('M d, Y') . '. Update the license record before assigning this driver.';
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->role($user);

        if ($role === self::ROLE_DRIVER) {
            abort(403, 'Your account type does not have access to Driver Assignments.');
        }

        if ($request->ajax()) {
            return $this->ajaxList($request, $user, $role);
        }

        $hasBroadVisibility = in_array($role, self::BROAD_VISIBILITY_ROLES, true);
        $canManage = $role !== self::ROLE_VIEWER;

        $scope = function ($q) use ($user, $role) {
            $this->scopeToVisibleVehicles($q, $user, $role);
        };

        $vehicleScope = Vehicle::query()->excludingDisposed();
        $scope($vehicleScope);

        $stats = [
            'total'      => (clone $vehicleScope)->count(),
            'assigned'   => (clone $vehicleScope)->whereNotNull('assigned_driver_id')->count(),
            'unassigned' => (clone $vehicleScope)->whereNull('assigned_driver_id')->count(),
        ];

        // Active drivers not currently the assigned_driver_id of any (non-
        // disposed) vehicle in this role's scope — "available to assign"
        // at a glance, same spirit as the "Unassigned Vehicles" count above.
        $driverScope = Driver::where('status', 'active');
        if ($role === self::ROLE_UNIT_ADMIN || $role === self::ROLE_STATION_ADMIN) {
            // Drivers aren't themselves unit/station-scoped in this schema,
            // so "available" here is scoped by exclusion against this
            // role's own visible vehicles rather than the driver roster.
            $assignedIds = (clone $vehicleScope)->whereNotNull('assigned_driver_id')->pluck('assigned_driver_id');
            $stats['available_drivers'] = $driverScope->whereNotIn('id', $assignedIds)->count();
        } else {
            $stats['available_drivers'] = $driverScope->whereDoesntHave('vehicles')->count();
        }

        $units = $hasBroadVisibility ? Unit::orderBy('unit_name')->get() : Unit::where('id', $user->unit_id)->orderBy('unit_name')->get();
        $stations = $hasBroadVisibility
            ? Station::orderBy('station_name')->get()
            : ($role === self::ROLE_UNIT_ADMIN
                ? Station::where('unit_id', $user->unit_id)->orderBy('station_name')->get()
                : Station::where('id', $user->station_id)->orderBy('station_name')->get());

        $drivers = Driver::where('status', 'active')->orderBy('lastname')->get(['id', 'firstname', 'lastname', 'license_expiration_date']);

        return view('driver_assignments.index', compact('stats', 'units', 'stations', 'drivers', 'hasBroadVisibility', 'canManage'));
    }

    protected function ajaxList(Request $request, $user, string $role): JsonResponse
    {
        $query = Vehicle::with(['driver', 'unit', 'station'])->excludingDisposed();
        $this->scopeToVisibleVehicles($query, $user, $role);

        if ($status = $request->get('assignment_status')) {
            if ($status === 'assigned') {
                $query->whereNotNull('assigned_driver_id');
            } elseif ($status === 'unassigned') {
                $query->whereNull('assigned_driver_id');
            }
        }
        if ($unitId = $request->get('unit_id')) {
            $query->where('unit_id', $unitId);
        }
        if ($stationId = $request->get('station_id')) {
            $query->where('station_id', $stationId);
        }

        if ($search = trim((string) $request->input('search.value'))) {
            $query->where(function ($q) use ($search) {
                $q->where('plate_number', 'like', "%{$search}%")
                  ->orWhere('make', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhereHas('driver', fn ($dq) => $dq->where('lastname', 'like', "%{$search}%")->orWhere('firstname', 'like', "%{$search}%"));
            });
        }

        $totalScoped = Vehicle::query()->excludingDisposed();
        $this->scopeToVisibleVehicles($totalScoped, $user, $role);
        $totalRecords = $totalScoped->count();
        $filteredRecords = (clone $query)->count();

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $vehicles = $query->orderBy('plate_number')->skip($start)->take($length)->get();

        $canManage = $role !== self::ROLE_VIEWER;

        // Current (still-open) assignment per vehicle, for the "assigned
        // since" column — one extra indexed query rather than N+1, keyed
        // by vehicle_id.
        $activeAssignments = DriverAssignment::active()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->get()
            ->keyBy('vehicle_id');

        $data = [];
        foreach ($vehicles as $v) {
            $vehicleHtml = '<span class="plate-badge">' . e(strtoupper($v->plate_number)) . '</span>' .
                '<div class="vehicle-sub-info mt-1">' . e(trim($v->make . ' ' . $v->model)) . '</div>';

            $scopeHtml = '<div class="vehicle-sub-info">' . e($v->unit->unit_name ?? '—') . '</div>' .
                '<div class="vehicle-sub-info">' . e($v->station->station_name ?? '—') . '</div>';

            if ($v->driver) {
                $driverHtml = '<div class="vehicle-main-name" style="font-size:13px;">' . e(trim($v->driver->firstname . ' ' . $v->driver->lastname)) . '</div>' .
                    ($v->driver->isLicenseExpired() ? '<div class="vehicle-sub-info text-danger"><i class="fas fa-triangle-exclamation"></i> License expired</div>' : '');
            } else {
                $driverHtml = '<span class="badge badge-light text-dark">Unassigned</span>';
            }

            $active = $activeAssignments->get($v->id);
            $sinceHtml = $active
                ? '<div class="vehicle-sub-info">' . $active->assigned_at->format('M d, Y') . '</div><div class="vehicle-sub-info">' . $active->assigned_at->diffForHumans() . '</div>'
                : '<span class="text-muted">—</span>';

            $actionsHtml = '';
            if ($canManage) {
                $actionsHtml .= '<button type="button" class="btn btn-sm btn-primary assign-reg-btn" data-id="' . $v->id . '" data-plate="' . e(strtoupper($v->plate_number)) . '" data-current-driver="' . ($v->assigned_driver_id ?? '') . '">' .
                    ($v->driver ? 'Reassign' : 'Assign') . '</button> ';
                if ($v->driver) {
                    $actionsHtml .= '<button type="button" class="btn btn-sm btn-outline-danger unassign-reg-btn" data-id="' . $v->id . '" data-plate="' . e(strtoupper($v->plate_number)) . '">Unassign</button> ';
                }
            }
            $actionsHtml .= '<button type="button" class="btn btn-sm btn-light border history-reg-btn" data-id="' . $v->id . '" data-plate="' . e(strtoupper($v->plate_number)) . '" title="Assignment history"><i class="fas fa-history"></i></button>';

            $data[] = [
                'vehicle_html'  => $vehicleHtml,
                'scope_html'    => $scopeHtml,
                'driver_html'   => $driverHtml,
                'since_html'    => $sinceHtml,
                'actions_html'  => $actionsHtml,
            ];
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function assign(Request $request, Vehicle $vehicle): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $this->authorizeWrite($vehicle, $user, $role);

        $validated = $request->validate([
            'driver_id' => ['required', 'exists:drivers,id'],
            'notes'     => ['nullable', 'string', 'max:255'],
        ]);

        if ($expiredDriverError = $this->expiredLicenseAssignmentError($validated['driver_id'])) {
            return response()->json(['success' => false, 'message' => $expiredDriverError], 422);
        }

        $previousDriverId = $vehicle->assigned_driver_id;
        $driver = Driver::findOrFail($validated['driver_id']);

        if ($previousDriverId == $validated['driver_id']) {
            return response()->json(['success' => false, 'message' => trim($driver->firstname . ' ' . $driver->lastname) . ' is already assigned to this vehicle.'], 422);
        }

        // Close this vehicle's currently-open assignment (if any) before
        // opening the new one — a vehicle only ever has one active
        // assignment at a time.
        DriverAssignment::where('vehicle_id', $vehicle->id)->active()->update([
            'unassigned_at' => now(),
            'unassigned_by' => $user->id,
        ]);

        DriverAssignment::create([
            'vehicle_id'   => $vehicle->id,
            'driver_id'    => $validated['driver_id'],
            'assigned_by'  => $user->id,
            'assigned_at'  => now(),
            'notes'        => $validated['notes'] ?? null,
        ]);

        $vehicle->update(['assigned_driver_id' => $validated['driver_id']]);

        ActivityLog::record(
            'updated',
            'Driver Assignment',
            ($previousDriverId ? 'Reassigned' : 'Assigned') . ' [' . strtoupper($vehicle->plate_number) . '] to ' . trim($driver->firstname . ' ' . $driver->lastname) . '.',
            $vehicle,
            ['before' => ['assigned_driver_id' => $previousDriverId], 'after' => ['assigned_driver_id' => $validated['driver_id']]]
        );

        return response()->json([
            'success' => true,
            'message' => '[' . strtoupper($vehicle->plate_number) . '] assigned to ' . trim($driver->firstname . ' ' . $driver->lastname) . '.',
        ]);
    }

    public function unassign(Request $request, Vehicle $vehicle): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $this->authorizeWrite($vehicle, $user, $role);

        if (! $vehicle->assigned_driver_id) {
            return response()->json(['success' => false, 'message' => 'This vehicle has no driver currently assigned.'], 422);
        }

        $driver = Driver::find($vehicle->assigned_driver_id);
        $driverName = $driver ? trim($driver->firstname . ' ' . $driver->lastname) : 'the assigned driver';

        DriverAssignment::where('vehicle_id', $vehicle->id)->active()->update([
            'unassigned_at' => now(),
            'unassigned_by' => $user->id,
        ]);

        $previousDriverId = $vehicle->assigned_driver_id;
        $vehicle->update(['assigned_driver_id' => null]);

        ActivityLog::record(
            'updated',
            'Driver Assignment',
            'Unassigned ' . $driverName . ' from [' . strtoupper($vehicle->plate_number) . '].',
            $vehicle,
            ['before' => ['assigned_driver_id' => $previousDriverId], 'after' => ['assigned_driver_id' => null]]
        );

        return response()->json([
            'success' => true,
            'message' => $driverName . ' unassigned from [' . strtoupper($vehicle->plate_number) . '].',
        ]);
    }

    /**
     * Full assignment history for one vehicle, newest first — a simple
     * unpaginated list (same pattern as VehicleController::getHistory()'s
     * OR/CR history modal) since a vehicle realistically accumulates a
     * handful of reassignments, not hundreds.
     */
    public function history(Vehicle $vehicle): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);

        if (! $this->canView($vehicle, $user, $role)) {
            return response()->json(['error' => 'Unauthorized Access'], 403);
        }

        $records = DriverAssignment::with(['driver', 'assignedByUser', 'unassignedByUser'])
            ->where('vehicle_id', $vehicle->id)
            ->orderByDesc('assigned_at')
            ->get()
            ->map(function (DriverAssignment $a) {
                return [
                    'driver_name'     => $a->driver ? trim($a->driver->firstname . ' ' . $a->driver->lastname) : 'Unknown driver',
                    'assigned_at'     => $a->assigned_at->format('M d, Y h:i A'),
                    'assigned_by'     => $a->assignedByUser->fullname ?? 'Unknown user',
                    'unassigned_at'   => $a->unassigned_at ? $a->unassigned_at->format('M d, Y h:i A') : null,
                    'unassigned_by'   => $a->unassignedByUser->fullname ?? null,
                    'duration'        => $a->assigned_at->diffForHumans($a->unassigned_at ?? now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]),
                    'is_active'       => $a->unassigned_at === null,
                    'notes'           => $a->notes,
                ];
            });

        return response()->json([
            'plate_number' => $vehicle->plate_number,
            'make_model'   => trim($vehicle->make . ' ' . $vehicle->model),
            'records'      => $records,
        ]);
    }
}
