<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Station;
use App\Models\TripRegistration;
use App\Models\Unit;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The Register → Assign Personnel → Approve paperwork ahead of a trip.
 * See TripRegistration's own docblock for exactly which trips need this at
 * all — only a SCHEDULED trip or one with an assistant assigned; a routine
 * solo errand still skips it entirely and starts instantly from the app
 * (Api\TripLogController::store()), unchanged.
 *
 * Both registering and approving happen here, on the admin side — once a
 * registration is approved, the driver app surfaces it as ready to start
 * (see Api\TripRegistrationController). Visibility/permission shape
 * mirrors TripLogController exactly: SUPER ADMINISTRATOR/ADMINISTRATOR/
 * VIEWER see the whole fleet (VIEWER read-only, can't register, approve,
 * reject or cancel anything — same as everywhere else), UNIT
 * ADMINISTRATOR is scoped to their unit, STATION ADMINISTRATOR to their
 * station. DRIVER accounts don't use this page — they see their own
 * registrations in the mobile app.
 */
class TripRegistrationController extends Controller
{
    protected const ROLE_SUPER_ADMIN = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER = 'VIEWER';
    protected const ROLE_DRIVER = 'DRIVER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    // Everyone who can register/approve/reject/cancel — notably excludes
    // VIEWER (read-only everywhere) and DRIVER (uses the mobile app).
    protected const MANAGE_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_UNIT_ADMIN, self::ROLE_STATION_ADMIN];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Identical pattern to TripLogController::scopeToVisibleVehicles() —
     * $viaRelation = true for a TripRegistration query (vehicle is a
     * relation), false for a Vehicle query directly (the vehicle picker).
     */
    protected function scopeToVisibleVehicles($query, $user, string $role, bool $viaRelation = false): void
    {
        $apply = function ($q) use ($user, $role) {
            if ($role === self::ROLE_UNIT_ADMIN) {
                $q->where('unit_id', $user->unit_id);
            } elseif ($role === self::ROLE_STATION_ADMIN) {
                $q->where('station_id', $user->station_id);
            }
        };

        if (in_array($role, self::BROAD_VISIBILITY_ROLES, true)) {
            return;
        }

        if ($viaRelation) {
            $query->whereHas('vehicle', $apply);
        } else {
            $apply($query);
        }
    }

    protected function assertCanManage(string $role): void
    {
        if (! in_array($role, self::MANAGE_ROLES, true)) {
            abort(403, 'Your account type does not have access to Trip Registrations.');
        }
    }

    /**
     * A Unit/Station Administrator can only act on a registration whose
     * vehicle is within their own unit/station — prevents acting on
     * someone else's registration just by guessing/typing its id in the URL.
     */
    protected function assertVisible(TripRegistration $registration, $user, string $role): void
    {
        if (in_array($role, self::BROAD_VISIBILITY_ROLES, true)) {
            return;
        }

        $vehicle = $registration->vehicle;
        if ($role === self::ROLE_UNIT_ADMIN && (! $vehicle || $vehicle->unit_id !== $user->unit_id)) {
            abort(403);
        }
        if ($role === self::ROLE_STATION_ADMIN && (! $vehicle || $vehicle->station_id !== $user->station_id)) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->role($user);

        if ($role === self::ROLE_DRIVER) {
            abort(403, 'Your account type does not have access to Trip Registrations.');
        }

        if ($request->ajax()) {
            return $this->ajaxList($request, $user, $role);
        }

        $hasBroadVisibility = in_array($role, self::BROAD_VISIBILITY_ROLES, true);

        $vehicleScope = Vehicle::query();
        $this->scopeToVisibleVehicles($vehicleScope, $user, $role);
        $vehicles = $vehicleScope->orderBy('plate_number')->get(['id', 'plate_number', 'make', 'model']);

        // Not scoped by unit/station — a Logistics Run's assistant may
        // come from anywhere in the roster, same as how the existing
        // driver picker elsewhere in VMIS isn't scoped either.
        // license_expiration_date is included so the Register Trip modal
        // can warn inline the moment an expired-license driver is picked,
        // ahead of the server-side block in store().
        $drivers = Driver::orderBy('lastname')->get(['id', 'firstname', 'lastname', 'license_expiration_date']);

        $units = $hasBroadVisibility ? Unit::orderBy('unit_name')->get() : Unit::where('id', $user->unit_id)->orderBy('unit_name')->get();
        $stations = $hasBroadVisibility
            ? Station::orderBy('station_name')->get()
            : ($role === self::ROLE_UNIT_ADMIN
                ? Station::where('unit_id', $user->unit_id)->orderBy('station_name')->get()
                : Station::where('id', $user->station_id)->orderBy('station_name')->get());

        $canManage = in_array($role, self::MANAGE_ROLES, true);

        $statsScope = function () use ($user, $role) {
            $q = TripRegistration::query();
            $this->scopeToVisibleVehicles($q, $user, $role, viaRelation: true);
            return $q;
        };
        $stats = [
            'total' => $statsScope()->count(),
            'pending' => $statsScope()->where('status', 'pending_approval')->count(),
            'active' => $statsScope()->whereIn('status', ['approved', 'active'])->count(),
            'completed_month' => $statsScope()->where('status', 'completed')->whereMonth('updated_at', now()->month)->whereYear('updated_at', now()->year)->count(),
        ];

        return view('trip_registrations.index', compact('vehicles', 'drivers', 'units', 'stations', 'hasBroadVisibility', 'canManage', 'stats'));
    }

    protected function ajaxList(Request $request, $user, string $role): JsonResponse
    {
        $query = TripRegistration::with(['vehicle', 'driver', 'assistantDriver', 'registeredBy', 'approvedBy'])
            ->orderByRaw("FIELD(status, 'pending_approval', 'approved', 'active', 'completed', 'rejected', 'cancelled')")
            ->orderByDesc('created_at');
        $this->scopeToVisibleVehicles($query, $user, $role, viaRelation: true);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($vehicleId = $request->get('vehicle_id')) {
            $query->where('vehicle_id', $vehicleId);
        }
        if ($tripType = $request->get('trip_type')) {
            $query->where('trip_type', $tripType);
        }
        if ($driverId = $request->get('driver_id')) {
            $query->where(function ($q) use ($driverId) {
                $q->where('driver_id', $driverId)->orWhere('assistant_driver_id', $driverId);
            });
        }
        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('scheduled_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('scheduled_date', '<=', $dateTo);
        }

        if ($search = trim((string) $request->input('search.value'))) {
            $query->where(function ($q) use ($search) {
                $q->where('origin', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%")
                  ->orWhereHas('vehicle', fn ($vq) => $vq->where('plate_number', 'like', "%{$search}%"))
                  ->orWhereHas('driver', fn ($dq) => $dq->where('lastname', 'like', "%{$search}%"));
            });
        }

        $totalScoped = TripRegistration::query();
        $this->scopeToVisibleVehicles($totalScoped, $user, $role, viaRelation: true);
        $totalRecords = $totalScoped->count();
        $filteredRecords = (clone $query)->count();

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $registrations = $query->skip($start)->take($length)->get();

        $canManage = in_array($role, self::MANAGE_ROLES, true);

        $data = [];
        foreach ($registrations as $r) {
            $vehicle = $r->vehicle;
            $vehicleHtml = $vehicle
                ? '<span class="plate-badge">' . e(strtoupper($vehicle->plate_number)) . '</span>' .
                  '<div class="vehicle-sub-info mt-1">' . e(trim($vehicle->make . ' ' . $vehicle->model)) . '</div>'
                : '<span class="text-muted">Vehicle removed</span>';

            $driverName = $r->driver ? trim($r->driver->firstname . ' ' . $r->driver->lastname) : '—';
            $personnelHtml = '<div class="vehicle-sub-info">' . e($driverName) . '</div>';
            if ($r->assistantDriver) {
                $personnelHtml .= '<div class="vehicle-sub-info text-muted">+ ' . e(trim($r->assistantDriver->firstname . ' ' . $r->assistantDriver->lastname)) . ' (assistant)</div>';
            }

            $typeLabel = ucfirst($r->trip_type);
            $typeBadgeClass = $r->trip_type === 'emergency' ? 'badge-danger' : ($r->trip_type === 'scheduled' ? 'badge-info' : 'badge-secondary');
            $tripHtml = '<span class="badge ' . $typeBadgeClass . ' mb-1">' . e($typeLabel) . '</span>';
            if ($r->round_trip) {
                $tripHtml .= ' <span class="badge badge-light text-dark mb-1" title="Round trip"><i class="fas fa-rotate"></i> Round Trip</span>';
            }
            $tripHtml .= '<div class="vehicle-main-name" style="font-size:13px;">' . e($r->origin) . ' → ' . e($r->destination) . '</div>';
            if ($r->purpose) {
                $tripHtml .= '<div class="vehicle-sub-info">' . e(Str::limit($r->purpose, 60)) . '</div>';
            }

            $scheduleHtml = $r->scheduled_date
                ? $r->scheduled_date->format('M d, Y') . ($r->scheduled_departure_time ? '<div class="vehicle-sub-info">' . date('h:i A', strtotime($r->scheduled_departure_time)) . '</div>' : '')
                : '<span class="text-muted">As soon as approved</span>';

            $statusMap = [
                'pending_approval' => ['Pending Approval', 'badge-warning'],
                'approved' => ['Approved — Not Started', 'badge-primary'],
                'active' => ['In Progress', 'badge-success'],
                'completed' => ['Completed', 'badge-secondary'],
                'rejected' => ['Rejected', 'badge-danger'],
                'cancelled' => ['Cancelled', 'badge-light text-dark'],
            ];
            [$statusLabel, $statusClass] = $statusMap[$r->status] ?? [ucfirst($r->status), 'badge-light'];
            $statusHtml = '<span class="badge ' . $statusClass . '">' . e($statusLabel) . '</span>';
            if ($r->status === 'rejected' && $r->rejection_reason) {
                $statusHtml .= '<div class="vehicle-sub-info mt-1">' . e(Str::limit($r->rejection_reason, 60)) . '</div>';
            }
            if ($r->status === 'completed' && $r->trip_log_id) {
                $statusHtml .= '<div class="vehicle-sub-info mt-1"><a href="' . route('trip-logs.index') . '">View in Trip Logs</a></div>';
            }

            $actionsHtml = '';
            if ($canManage) {
                if ($r->status === 'pending_approval') {
                    $actionsHtml .= '<button type="button" class="btn btn-success btn-sm approve-reg-btn" data-id="' . $r->id . '"><i class="fas fa-check"></i> Approve</button> ';
                    $actionsHtml .= '<button type="button" class="btn btn-outline-danger btn-sm reject-reg-btn" data-id="' . $r->id . '">Reject</button> ';
                    $actionsHtml .= '<button type="button" class="btn btn-outline-secondary btn-sm cancel-reg-btn" data-id="' . $r->id . '">Cancel</button>';
                } elseif ($r->status === 'approved') {
                    $actionsHtml .= '<button type="button" class="btn btn-outline-secondary btn-sm cancel-reg-btn" data-id="' . $r->id . '">Cancel</button>';
                }
            }
            if ($actionsHtml === '') {
                $actionsHtml = '<span class="text-muted">—</span>';
            }

            $data[] = [
                'vehicle_html' => $vehicleHtml,
                'personnel_html' => $personnelHtml,
                'trip_html' => $tripHtml,
                'schedule_html' => $scheduleHtml,
                'status_html' => $statusHtml,
                'actions_html' => $actionsHtml,
            ];
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $this->assertCanManage($role);

        $validated = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'assistant_driver_id' => ['nullable', 'exists:drivers,id', 'different:driver_id'],
            'trip_type' => ['required', 'in:scheduled,unscheduled,emergency'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'origin' => ['required', 'string', 'max:150'],
            'destination' => ['required', 'string', 'max:150'],
            'scheduled_date' => ['required_if:trip_type,scheduled', 'nullable', 'date'],
            'scheduled_departure_time' => ['nullable', 'date_format:H:i'],
            'round_trip' => ['nullable', 'boolean'],
        ]);

        // The chosen vehicle must actually be within this admin's own
        // unit/station — a Unit/Station Administrator can't register a
        // trip for a vehicle outside their scope just by picking its id,
        // same defense every other module in VMIS already applies.
        $vehicleQuery = Vehicle::where('id', $validated['vehicle_id']);
        $this->scopeToVisibleVehicles($vehicleQuery, $user, $role);
        $vehicle = $vehicleQuery->first();
        if (! $vehicle) {
            return response()->json(['success' => false, 'message' => 'That vehicle is outside your assigned scope.'], 403);
        }

        // A driver (or assistant driver) with an expired Driver's License
        // cannot be put on a trip at all — surfaced here as a blocking
        // validation error so the admin sees exactly why, same wording
        // used in VehicleController's assignment guard for consistency.
        $driver = Driver::find($validated['driver_id']);
        if ($driver && $driver->isLicenseExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'This trip cannot be registered — ' . trim($driver->firstname . ' ' . $driver->lastname) . "'s Driver's License expired on " . $driver->license_expiration_date->format('M d, Y') . '. Update the license record before registering a trip for this driver.',
            ], 422);
        }
        if (! empty($validated['assistant_driver_id'])) {
            $assistant = Driver::find($validated['assistant_driver_id']);
            if ($assistant && $assistant->isLicenseExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This trip cannot be registered — the assistant driver, ' . trim($assistant->firstname . ' ' . $assistant->lastname) . "'s Driver's License expired on " . $assistant->license_expiration_date->format('M d, Y') . '. Update the license record before assigning this driver.',
                ], 422);
            }
        }

        $isEmergency = $validated['trip_type'] === 'emergency';

        $registration = TripRegistration::create([
            ...$validated,
            'round_trip' => $validated['round_trip'] ?? false,
            'status' => $isEmergency ? 'approved' : 'pending_approval',
            'registered_by' => $user->id,
            // Emergency trips are auto-approved at the moment of
            // registration — see TripRegistration's docblock on why this
            // never blocks on anyone being online to sign off.
            'approved_by' => $isEmergency ? $user->id : null,
            'approved_at' => $isEmergency ? now() : null,
        ]);

        ActivityLog::record(
            'created',
            'Trip Registration',
            ($isEmergency ? 'Registered an EMERGENCY trip (auto-approved) ' : 'Registered a ' . $validated['trip_type'] . ' trip ')
                . 'for [' . strtoupper($vehicle->plate_number) . '] (' . $validated['origin'] . ' to ' . $validated['destination'] . ').',
            $registration,
            ['after' => $validated]
        );

        return response()->json([
            'success' => true,
            'message' => $isEmergency
                ? 'Emergency trip registered and auto-approved — the driver can start tracking immediately.'
                : 'Trip registered — awaiting approval before the driver can start.',
        ]);
    }

    public function approve(Request $request, TripRegistration $tripRegistration): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $this->assertCanManage($role);
        $this->assertVisible($tripRegistration, $user, $role);

        if ($tripRegistration->status !== 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'This registration is no longer awaiting approval.'], 422);
        }

        $tripRegistration->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        ActivityLog::record('updated', 'Trip Registration', 'Approved trip registration #' . $tripRegistration->id . '.', $tripRegistration);

        return response()->json(['success' => true, 'message' => 'Trip approved — the driver can now start tracking from the app.']);
    }

    public function reject(Request $request, TripRegistration $tripRegistration): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $this->assertCanManage($role);
        $this->assertVisible($tripRegistration, $user, $role);

        if ($tripRegistration->status !== 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'This registration is no longer awaiting approval.'], 422);
        }

        $validated = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:255']]);

        $tripRegistration->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        ActivityLog::record('updated', 'Trip Registration', 'Rejected trip registration #' . $tripRegistration->id . '.', $tripRegistration);

        return response()->json(['success' => true, 'message' => 'Trip registration rejected.']);
    }

    public function cancel(Request $request, TripRegistration $tripRegistration): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $this->assertCanManage($role);
        $this->assertVisible($tripRegistration, $user, $role);

        if (! in_array($tripRegistration->status, ['pending_approval', 'approved'], true)) {
            return response()->json(['success' => false, 'message' => 'Only a pending or approved registration can be cancelled.'], 422);
        }

        $tripRegistration->update(['status' => 'cancelled']);

        ActivityLog::record('updated', 'Trip Registration', 'Cancelled trip registration #' . $tripRegistration->id . '.', $tripRegistration);

        return response()->json(['success' => true, 'message' => 'Trip registration cancelled.']);
    }
}
