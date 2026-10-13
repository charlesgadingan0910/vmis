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
 *
 * index()/ajaxList()/history() below are the original VEHICLE-centric
 * flow (pick a vehicle, assign/reassign/unassign its driver) that used to
 * back its own standalone "Driver Assignments" page. That page has since
 * been folded into Driver Management per request — DriverController now
 * renders the merged page, and assignToDriver()/unassignDriver()/
 * driverHistory() below are the DRIVER-centric equivalents it actually
 * calls (pick a driver, assign/reassign/unassign their vehicle). The
 * vehicle-centric methods are left in place rather than deleted — nothing
 * currently links to them (routes/web.php now redirects
 * driver-assignments.index straight to drivers.index), but they're
 * harmless to keep and still pass the same authorization rules.
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
     * Non-aborting version of the check authorizeWrite() enforces below —
     * used by the driver-centric methods, which need to ask "can I touch
     * this OTHER vehicle too" without blowing up the whole request when
     * the answer is no (they report a clear message instead; see
     * assignToDriver()/unassignDriver()).
     */
    protected function canWriteVehicle(Vehicle $vehicle, $user, string $role): bool
    {
        if ($role === self::ROLE_VIEWER || $role === self::ROLE_DRIVER) {
            return false;
        }
        if ($role === self::ROLE_UNIT_ADMIN && $vehicle->unit_id != $user->unit_id) {
            return false;
        }
        if ($role === self::ROLE_STATION_ADMIN && $vehicle->station_id != $user->station_id) {
            return false;
        }
        return true;
    }

    /**
     * Same shape as VehicleController::authorizeVehicleWrite() — VIEWER
     * blocked outright, UNIT/STATION admin confined to their own vehicles.
     */
    protected function authorizeWrite(Vehicle $vehicle, $user, string $role): void
    {
        if (! $this->canWriteVehicle($vehicle, $user, $role)) {
            abort(403, ($role === self::ROLE_VIEWER || $role === self::ROLE_DRIVER)
                ? 'Your account type cannot change vehicle assignments.'
                : 'Unauthorized Action');
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

    // ------------------------------------------------------------------
    // Driver-centric methods — back the merged Driver Management page
    // (DriverController::index()/ajaxList()). See this class's docblock.
    // ------------------------------------------------------------------

    /**
     * Assign (or reassign) a vehicle TO a driver, chosen from the Driver
     * Management page. Mirrors assign() above but enforces an extra rule
     * that page's design needs: a driver has at most one active vehicle
     * at a time. If this driver already has a different active vehicle,
     * that old assignment is closed first — but only when the current
     * user can actually write to that old vehicle too, so a Unit/Station
     * Administrator can't reach into another unit/station's vehicle
     * through this shortcut; if they can't, the whole request is
     * rejected with a message explaining why, rather than silently
     * leaving the driver double-booked.
     */
    public function assignToDriver(Request $request, Driver $driver): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);

        $validated = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'notes'      => ['nullable', 'string', 'max:255'],
        ]);

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $this->authorizeWrite($vehicle, $user, $role);

        if ($expiredError = $this->expiredLicenseAssignmentError($driver->id)) {
            return response()->json(['success' => false, 'message' => $expiredError], 422);
        }

        if ($vehicle->assigned_driver_id == $driver->id) {
            return response()->json(['success' => false, 'message' => trim($driver->firstname . ' ' . $driver->lastname) . ' is already assigned to this vehicle.'], 422);
        }

        $otherActive = DriverAssignment::active()->where('driver_id', $driver->id)->where('vehicle_id', '!=', $vehicle->id)->get();

        // Check scope on every other active assignment BEFORE closing any
        // of them — an all-or-nothing check, not a partial one.
        foreach ($otherActive as $openAssignment) {
            $otherVehicle = Vehicle::find($openAssignment->vehicle_id);
            if ($otherVehicle && ! $this->canWriteVehicle($otherVehicle, $user, $role)) {
                return response()->json([
                    'success' => false,
                    'message' => trim($driver->firstname . ' ' . $driver->lastname) . ' is already assigned to [' . strtoupper($otherVehicle->plate_number) . '], which is outside your management scope. Ask an administrator with access to that vehicle to unassign it first.',
                ], 422);
            }
        }

        foreach ($otherActive as $openAssignment) {
            $otherVehicle = Vehicle::find($openAssignment->vehicle_id);
            $openAssignment->update(['unassigned_at' => now(), 'unassigned_by' => $user->id]);
            if ($otherVehicle) {
                $otherVehicle->update(['assigned_driver_id' => null]);
            }
        }

        $previousDriverId = $vehicle->assigned_driver_id;

        // Close this vehicle's own currently-open assignment (if any)
        // before opening the new one — a vehicle only ever has one active
        // assignment at a time, same rule assign() above enforces.
        DriverAssignment::where('vehicle_id', $vehicle->id)->active()->update([
            'unassigned_at' => now(),
            'unassigned_by' => $user->id,
        ]);

        DriverAssignment::create([
            'vehicle_id'  => $vehicle->id,
            'driver_id'   => $driver->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'notes'       => $validated['notes'] ?? null,
        ]);

        $vehicle->update(['assigned_driver_id' => $driver->id]);

        ActivityLog::record(
            'updated',
            'Driver Assignment',
            ($previousDriverId ? 'Reassigned' : 'Assigned') . ' [' . strtoupper($vehicle->plate_number) . '] to ' . trim($driver->firstname . ' ' . $driver->lastname) . '.',
            $vehicle,
            ['before' => ['assigned_driver_id' => $previousDriverId], 'after' => ['assigned_driver_id' => $driver->id]]
        );

        return response()->json([
            'success' => true,
            'message' => trim($driver->firstname . ' ' . $driver->lastname) . ' assigned to [' . strtoupper($vehicle->plate_number) . '].',
        ]);
    }

    /**
     * Unassign a driver from ALL of their currently active vehicles
     * (normally just one). Any that are outside the current user's
     * management scope are skipped rather than failing the whole
     * request — the response message says which plates were actually
     * unassigned and which were left alone.
     */
    public function unassignDriver(Driver $driver): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);

        if ($role === self::ROLE_VIEWER || $role === self::ROLE_DRIVER) {
            abort(403, 'Your account type cannot change vehicle assignments.');
        }

        $activeAssignments = DriverAssignment::active()->where('driver_id', $driver->id)->get();

        if ($activeAssignments->isEmpty()) {
            return response()->json(['success' => false, 'message' => trim($driver->firstname . ' ' . $driver->lastname) . ' has no vehicle currently assigned.'], 422);
        }

        $unassignedPlates = [];
        $skippedPlates = [];

        foreach ($activeAssignments as $assignment) {
            $vehicle = Vehicle::find($assignment->vehicle_id);
            if (! $vehicle) {
                continue;
            }
            if (! $this->canWriteVehicle($vehicle, $user, $role)) {
                $skippedPlates[] = strtoupper($vehicle->plate_number);
                continue;
            }

            $assignment->update(['unassigned_at' => now(), 'unassigned_by' => $user->id]);
            $vehicle->update(['assigned_driver_id' => null]);
            $unassignedPlates[] = strtoupper($vehicle->plate_number);

            ActivityLog::record(
                'updated',
                'Driver Assignment',
                'Unassigned ' . trim($driver->firstname . ' ' . $driver->lastname) . ' from [' . strtoupper($vehicle->plate_number) . '].',
                $vehicle,
                ['before' => ['assigned_driver_id' => $driver->id], 'after' => ['assigned_driver_id' => null]]
            );
        }

        if (empty($unassignedPlates)) {
            return response()->json(['success' => false, 'message' => trim($driver->firstname . ' ' . $driver->lastname) . "'s vehicle(s) are outside your management scope."], 422);
        }

        $message = trim($driver->firstname . ' ' . $driver->lastname) . ' unassigned from [' . implode(', ', $unassignedPlates) . '].';
        if (! empty($skippedPlates)) {
            $message .= ' [' . implode(', ', $skippedPlates) . '] left unchanged — outside your management scope.';
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * Full assignment history for one driver across every vehicle they've
     * ever been the custodian of, newest first — the driver-centric
     * mirror of history(Vehicle $vehicle) above, used by the merged
     * Driver Management page's "Vehicle assignment history" button.
     */
    public function driverHistory(Driver $driver): JsonResponse
    {
        $role = $this->role(auth()->user());

        if ($role === self::ROLE_DRIVER) {
            return response()->json(['error' => 'Unauthorized Access'], 403);
        }

        $records = DriverAssignment::with(['vehicle', 'assignedByUser', 'unassignedByUser'])
            ->where('driver_id', $driver->id)
            ->orderByDesc('assigned_at')
            ->get()
            ->map(function (DriverAssignment $a) {
                $vehicleLabel = $a->vehicle
                    ? strtoupper($a->vehicle->plate_number) . ' — ' . trim($a->vehicle->make . ' ' . $a->vehicle->model)
                    : 'Unknown vehicle';

                return [
                    'vehicle_label' => $vehicleLabel,
                    'assigned_at'   => $a->assigned_at->format('M d, Y h:i A'),
                    'assigned_by'   => $a->assignedByUser->fullname ?? 'Unknown user',
                    'unassigned_at' => $a->unassigned_at ? $a->unassigned_at->format('M d, Y h:i A') : null,
                    'unassigned_by' => $a->unassignedByUser->fullname ?? null,
                    'duration'      => $a->assigned_at->diffForHumans($a->unassigned_at ?? now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]),
                    'is_active'     => $a->unassigned_at === null,
                    'notes'         => $a->notes,
                ];
            });

        return response()->json([
            'driver_name' => trim($driver->firstname . ' ' . $driver->lastname),
            'records'     => $records,
        ]);
    }
}
