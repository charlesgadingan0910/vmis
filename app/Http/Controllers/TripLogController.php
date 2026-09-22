<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Station;
use App\Models\TripLog;
use App\Models\Unit;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Trip Logs: a permanent record of every vehicle's trip history.
 *
 * Who can INPUT a trip: only a DRIVER (for their own assigned vehicle) and
 * SUPER ADMINISTRATOR (for any vehicle) — every other role is read-only here,
 * mirroring the Vehicle/Maintenance visibility scoping already used
 * elsewhere: SUPER ADMINISTRATOR, ADMINISTRATOR and VIEWER see every trip;
 * UNIT ADMINISTRATOR is scoped to their own unit; STATION ADMINISTRATOR is
 * scoped to their own station.
 *
 * Trip logs are never edited or deleted once created (see() store()) — the
 * same audit-trail treatment already given to ActivityLog.
 */
class TripLogController extends Controller
{
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';
    protected const ROLE_DRIVER        = 'DRIVER';

    // Roles that see every unit/station's trips, not just their own slice.
    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    // Every role permitted to open the admin (desktop) Trip Logs view at all.
    protected const ADMIN_VIEW_ROLES = [
        self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_UNIT_ADMIN, self::ROLE_STATION_ADMIN, self::ROLE_VIEWER,
    ];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Applies the current user's unit/station visibility to a vehicle-owning
     * query — identical pattern to MaintenanceController::scopeToVisibleVehicles.
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

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->role($user);

        if ($role === self::ROLE_DRIVER) {
            return $this->driverIndex($user);
        }

        if (! in_array($role, self::ADMIN_VIEW_ROLES, true)) {
            abort(403, 'Your account type does not have access to Trip Logs.');
        }

        if ($request->ajax()) {
            return $this->adminAjaxList($request, $user, $role);
        }

        return $this->adminIndex($user, $role);
    }

    /**
     * Mobile-first view for a DRIVER: a lightweight entry form for their own
     * assigned vehicle(s) plus their own trip history — no DataTables/heavy
     * admin JS, same convention as the QR scanner page.
     */
    protected function driverIndex($user)
    {
        $driver = $user->driver_id ? Driver::find($user->driver_id) : null;

        $vehicles = $driver
            ? Vehicle::where('assigned_driver_id', $driver->id)->orderBy('plate_number')->get()
            : collect();

        $trips = $driver
            ? TripLog::with('vehicle')->where('driver_id', $driver->id)->orderByDesc('trip_date')->orderByDesc('id')->paginate(15)
            : TripLog::whereRaw('0 = 1')->paginate(15);

        return view('trip_logs.mobile', compact('driver', 'vehicles', 'trips'));
    }

    protected function adminAjaxList(Request $request, $user, string $role): JsonResponse
    {
        $query = TripLog::with(['vehicle', 'driver', 'loggedBy'])->orderByDesc('trip_date')->orderByDesc('id');
        $this->scopeToVisibleVehicles($query, $user, $role, viaRelation: true);

        if ($vehicleId = $request->get('vehicle_id')) {
            $query->where('vehicle_id', $vehicleId);
        }
        if ($unitId = $request->get('unit_id')) {
            $query->whereHas('vehicle', fn ($q) => $q->where('unit_id', $unitId));
        }
        if ($stationId = $request->get('station_id')) {
            $query->whereHas('vehicle', fn ($q) => $q->where('station_id', $stationId));
        }
        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('trip_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('trip_date', '<=', $dateTo);
        }

        if ($search = trim((string) $request->input('search.value'))) {
            $query->where(function ($q) use ($search) {
                $q->where('origin', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%")
                  ->orWhereHas('vehicle', function ($vq) use ($search) {
                      $vq->where('plate_number', 'like', "%{$search}%")
                         ->orWhere('make', 'like', "%{$search}%")
                         ->orWhere('model', 'like', "%{$search}%");
                  });
            });
        }

        $totalScoped = TripLog::query();
        $this->scopeToVisibleVehicles($totalScoped, $user, $role, viaRelation: true);
        $totalRecords = $totalScoped->count();
        $filteredRecords = (clone $query)->count();

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $trips = $query->skip($start)->take($length)->get();

        $data = [];
        foreach ($trips as $trip) {
            $vehicle = $trip->vehicle;

            $vehicleHtml = $vehicle
                ? '<span class="plate-badge">' . e(strtoupper($vehicle->plate_number)) . '</span>' .
                  '<div class="vehicle-sub-info mt-1">' . e(trim($vehicle->make . ' ' . $vehicle->model)) . '</div>'
                : '<span class="text-muted">Vehicle removed</span>';

            $driverName = $trip->driver ? trim($trip->driver->firstname . ' ' . $trip->driver->lastname) : '—';
            $driverHtml = '<div class="vehicle-sub-info">' . e($driverName) . '</div>';

            $tripHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . $trip->trip_date->format('M d, Y') . '</div>';
            if ($trip->departure_time || $trip->arrival_time) {
                $dep = $trip->departure_time ? date('h:i A', strtotime($trip->departure_time)) : '—';
                $arr = $trip->arrival_time ? date('h:i A', strtotime($trip->arrival_time)) : '—';
                $tripHtml .= '<div class="vehicle-sub-info">' . $dep . ' to ' . $arr . '</div>';
            }

            $routeHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . e($trip->origin) . ' &rarr; ' . e($trip->destination) . '</div>';
            if ($trip->purpose) {
                $routeHtml .= '<div class="vehicle-sub-info">' . e(Str::limit($trip->purpose, 60)) . '</div>';
            }

            $odometerHtml = '<span class="text-muted">—</span>';
            if ($trip->odometer_start !== null || $trip->odometer_end !== null) {
                $odometerHtml = number_format((int) $trip->odometer_start) . ' &rarr; ' . number_format((int) $trip->odometer_end);
                if ($trip->odometer_start !== null && $trip->odometer_end !== null && $trip->odometer_end >= $trip->odometer_start) {
                    $odometerHtml .= '<div class="vehicle-sub-info mt-1">' . number_format($trip->odometer_end - $trip->odometer_start) . ' km traveled</div>';
                }
            }

            $loggedHtml = '<div class="vehicle-sub-info">' . e(optional($trip->loggedBy)->fullname ?? 'Unknown user') . '</div>' .
                          '<div class="vehicle-sub-info">' . $trip->created_at->format('M d, Y h:i A') . '</div>';

            $data[] = [
                'vehicle_html'  => $vehicleHtml,
                'driver_html'   => $driverHtml,
                'trip_html'     => $tripHtml,
                'route_html'    => $routeHtml,
                'odometer_html' => $odometerHtml,
                'logged_html'   => $loggedHtml,
            ];
        }

        return response()->json([
            'draw'            => intval($request->input('draw')),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }

    protected function adminIndex($user, string $role)
    {
        $hasBroadVisibility = in_array($role, self::BROAD_VISIBILITY_ROLES, true);

        $vehicleScope = Vehicle::query();
        $this->scopeToVisibleVehicles($vehicleScope, $user, $role);
        $vehicles = (clone $vehicleScope)->orderBy('plate_number')->get(['id', 'plate_number', 'make', 'model']);

        $tripScope = TripLog::query();
        $this->scopeToVisibleVehicles($tripScope, $user, $role, viaRelation: true);

        $stats = [
            'total_trips'     => (clone $tripScope)->count(),
            'logged_today'    => (clone $tripScope)->whereDate('trip_date', now()->toDateString())->count(),
            'logged_month'    => (clone $tripScope)->whereBetween('trip_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'vehicles_logged' => (clone $tripScope)->distinct('vehicle_id')->count('vehicle_id'),
        ];

        $units = $hasBroadVisibility
            ? Unit::orderBy('unit_name')->get()
            : Unit::where('id', $user->unit_id)->orderBy('unit_name')->get();

        if ($hasBroadVisibility) {
            $stations = Station::orderBy('station_name')->get();
        } elseif ($role === self::ROLE_UNIT_ADMIN) {
            $stations = Station::where('unit_id', $user->unit_id)->orderBy('station_name')->get();
        } else {
            $stations = Station::where('id', $user->station_id)->orderBy('station_name')->get();
        }

        // Only a SUPER ADMINISTRATOR may log a trip from this desktop view (a
        // DRIVER logs from the mobile view instead) — everyone else here is
        // read-only, per the spec.
        $canLogForAnyVehicle = ($role === self::ROLE_SUPER_ADMIN);
        $allVehicles = $canLogForAnyVehicle
            ? Vehicle::orderBy('plate_number')->get(['id', 'plate_number', 'make', 'model'])
            : collect();

        return view('trip_logs.index', compact(
            'stats', 'vehicles', 'units', 'stations', 'hasBroadVisibility', 'canLogForAnyVehicle', 'allVehicles'
        ));
    }

    /**
     * Log a new trip. Vehicle/driver resolution differs sharply by role:
     * a DRIVER can only ever log against their own assigned vehicle (server-
     * derived, never trusted from client input beyond picking among their
     * own vehicles if assigned more than one); a SUPER ADMINISTRATOR may log
     * for any vehicle via an explicit picker.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);

        if (! in_array($role, [self::ROLE_SUPER_ADMIN, self::ROLE_DRIVER], true)) {
            return response()->json(['success' => false, 'message' => 'Your account type is not permitted to log trips.'], 403);
        }

        $rules = [
            'trip_date'      => ['required', 'date'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'arrival_time'   => ['nullable', 'date_format:H:i', 'after_or_equal:departure_time'],
            'origin'         => ['required', 'string', 'max:150'],
            'destination'    => ['required', 'string', 'max:150'],
            'purpose'        => ['nullable', 'string', 'max:255'],
            'odometer_start' => ['nullable', 'integer', 'min:0'],
            'odometer_end'   => ['nullable', 'integer', 'min:0', 'gte:odometer_start'],
            'passengers'     => ['nullable', 'string', 'max:255'],
            'remarks'        => ['nullable', 'string', 'max:1000'],
        ];

        if ($role === self::ROLE_SUPER_ADMIN) {
            $rules['vehicle_id'] = ['required', 'exists:vehicles,id'];
        }

        $validated = $request->validate($rules);

        if ($role === self::ROLE_DRIVER) {
            $driver = $user->driver_id ? Driver::find($user->driver_id) : null;

            if (! $driver) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not linked to a driver profile yet. Contact your administrator.',
                ], 403);
            }

            $vehicle = Vehicle::where('assigned_driver_id', $driver->id)
                ->when($request->filled('vehicle_id'), fn ($q) => $q->where('id', $request->vehicle_id))
                ->first();

            if (! $vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'No vehicle is currently assigned to you. Contact your administrator.',
                ], 403);
            }

            $validated['vehicle_id'] = $vehicle->id;
            $validated['driver_id'] = $driver->id;
        } else {
            // SUPER ADMINISTRATOR: any vehicle, tagged with whoever is currently assigned to it (if anyone).
            $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
            $validated['driver_id'] = $vehicle->assigned_driver_id;
        }

        $validated['logged_by'] = $user->id;

        $trip = TripLog::create($validated);

        ActivityLog::record(
            'created',
            'Trip Log',
            'Logged a trip for [' . strtoupper($vehicle->plate_number) . '] (' . $validated['origin'] . ' to ' . $validated['destination'] . ').',
            $trip,
            ['after' => $validated]
        );

        return response()->json([
            'success' => true,
            'message' => 'Trip logged for [' . strtoupper($vehicle->plate_number) . '].',
        ]);
    }
}
