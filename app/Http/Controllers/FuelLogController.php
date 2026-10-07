<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\Station;
use App\Models\Unit;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Fuel Monitoring: a permanent record of every vehicle's refueling history,
 * plus the cost/efficiency figures derived from it.
 *
 * Deliberately its own module rather than columns bolted onto Trip Logs — a
 * refuel doesn't line up one-to-one with a trip (several trips can happen
 * between fill-ups, or a refuel can happen with no trip logged that day),
 * and fuel efficiency is computed between *consecutive refuels*, not
 * between trip legs. See FuelLog::distanceSinceLastRefuel()/kmPerLiter().
 *
 * Who can INPUT a refuel: only a DRIVER (for their own assigned vehicle) and
 * SUPER ADMINISTRATOR (for any vehicle) — every other role is read-only
 * here, the exact same visibility/permission shape as TripLogController:
 * SUPER ADMINISTRATOR, ADMINISTRATOR and VIEWER see every entry; UNIT
 * ADMINISTRATOR is scoped to their own unit; STATION ADMINISTRATOR is
 * scoped to their own station.
 *
 * Fuel logs are never edited or deleted once created (see() store()) — the
 * same audit-trail treatment already given to Trip Logs/Activity Log.
 */
class FuelLogController extends Controller
{
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';
    protected const ROLE_DRIVER        = 'DRIVER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    protected const ADMIN_VIEW_ROLES = [
        self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_UNIT_ADMIN, self::ROLE_STATION_ADMIN, self::ROLE_VIEWER,
    ];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Identical pattern to TripLogController::scopeToVisibleVehicles.
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
            abort(403, 'Your account type does not have access to Fuel Monitoring.');
        }

        if ($request->ajax()) {
            return $this->adminAjaxList($request, $user, $role);
        }

        return $this->adminIndex($user, $role);
    }

    /**
     * Mobile-first view for a DRIVER: a lightweight entry form for their own
     * assigned vehicle(s) plus their own refuel history — same convention as
     * TripLogController::driverIndex.
     */
    protected function driverIndex($user)
    {
        $driver = $user->driver_id ? Driver::find($user->driver_id) : null;

        $vehicles = $driver
            ? Vehicle::where('assigned_driver_id', $driver->id)->orderBy('plate_number')->get()
            : collect();

        $logs = $driver
            ? FuelLog::with('vehicle')->where('driver_id', $driver->id)->orderByDesc('refuel_date')->orderByDesc('id')->paginate(15)
            : FuelLog::whereRaw('0 = 1')->paginate(15);

        return view('fuel_logs.mobile', compact('driver', 'vehicles', 'logs'));
    }

    protected function adminAjaxList(Request $request, $user, string $role): JsonResponse
    {
        $query = FuelLog::with(['vehicle', 'driver', 'loggedBy'])->orderByDesc('refuel_date')->orderByDesc('id');
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
            $query->whereDate('refuel_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('refuel_date', '<=', $dateTo);
        }

        if ($search = trim((string) $request->input('search.value'))) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('vehicle', function ($vq) use ($search) {
                    $vq->where('plate_number', 'like', "%{$search}%")
                       ->orWhere('make', 'like', "%{$search}%")
                       ->orWhere('model', 'like', "%{$search}%");
                });
            });
        }

        $totalScoped = FuelLog::query();
        $this->scopeToVisibleVehicles($totalScoped, $user, $role, viaRelation: true);
        $totalRecords = $totalScoped->count();
        $filteredRecords = (clone $query)->count();

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $logs = $query->skip($start)->take($length)->get();

        $data = [];
        foreach ($logs as $log) {
            $vehicle = $log->vehicle;

            $vehicleHtml = $vehicle
                ? '<span class="plate-badge">' . e(strtoupper($vehicle->plate_number)) . '</span>' .
                  '<div class="vehicle-sub-info mt-1">' . e(trim($vehicle->make . ' ' . $vehicle->model)) . '</div>'
                : '<span class="text-muted">Vehicle removed</span>';

            $driverName = $log->driver ? trim($log->driver->firstname . ' ' . $log->driver->lastname) : '—';
            $driverHtml = '<div class="vehicle-sub-info">' . e($driverName) . '</div>';

            $dateHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . $log->refuel_date->format('M d, Y') . '</div>';

            $fuelHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . number_format((float) $log->liters, 2) . ' L</div>' .
                        '<div class="vehicle-sub-info">&#8369;' . number_format((float) $log->total_cost, 2) . ' &middot; &#8369;' . number_format($log->pricePerLiter() ?? 0, 2) . '/L</div>';

            $odometerHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . number_format((int) $log->odometer_reading) . ' km</div>';
            $distance = $log->distanceSinceLastRefuel();
            $kml = $log->kmPerLiter();
            if ($distance !== null && $kml !== null) {
                $odometerHtml .= '<div class="vehicle-sub-info">' . number_format($distance) . ' km since last &middot; ' . number_format($kml, 2) . ' km/L</div>';
            } else {
                $odometerHtml .= '<div class="vehicle-sub-info text-muted">First logged refuel for this vehicle</div>';
            }

            $receiptHtml = $log->receipt_path
                ? '<a href="' . route('fuel-logs.document', ['path' => $log->receipt_path]) . '" target="_blank" class="docs-pill-btn has-docs"><i class="fas fa-receipt"></i> Receipt</a>'
                : '<span class="text-muted">—</span>';

            $loggedHtml = '<div class="vehicle-sub-info">' . e(optional($log->loggedBy)->fullname ?? 'Unknown user') . '</div>' .
                          '<div class="vehicle-sub-info">' . $log->created_at->format('M d, Y h:i A') . '</div>';

            $data[] = [
                'vehicle_html'  => $vehicleHtml,
                'driver_html'   => $driverHtml,
                'date_html'     => $dateHtml,
                'fuel_html'     => $fuelHtml,
                'odometer_html' => $odometerHtml,
                'receipt_html'  => $receiptHtml,
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

        $logScope = FuelLog::query();
        $this->scopeToVisibleVehicles($logScope, $user, $role, viaRelation: true);

        $monthScope = (clone $logScope)->whereBetween('refuel_date', [now()->startOfMonth(), now()->endOfMonth()]);

        // Fleet-wide average km/L this month: computed in PHP (not SQL) since
        // it depends on each entry's own previous-refuel lookup — the same
        // per-entry logic FuelLog::kmPerLiter() already encapsulates, so it's
        // reused here rather than re-derived as a query.
        $kmlValues = (clone $monthScope)->get()->map(fn ($log) => $log->kmPerLiter())->filter(fn ($v) => $v !== null);

        $stats = [
            'total_logs'  => (clone $logScope)->count(),
            'liters_month'=> (clone $monthScope)->sum('liters'),
            'cost_month'  => (clone $monthScope)->sum('total_cost'),
            'avg_kml'     => $kmlValues->isNotEmpty() ? round($kmlValues->avg(), 2) : null,
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

        // Only a SUPER ADMINISTRATOR may log a refuel from this desktop view
        // (a DRIVER logs from the mobile view instead) — everyone else here
        // is read-only, same convention as Trip Logs.
        $canLogForAnyVehicle = ($role === self::ROLE_SUPER_ADMIN);
        $allVehicles = $canLogForAnyVehicle
            ? Vehicle::orderBy('plate_number')->get(['id', 'plate_number', 'make', 'model'])
            : collect();

        return view('fuel_logs.index', compact(
            'stats', 'vehicles', 'units', 'stations', 'hasBroadVisibility', 'canLogForAnyVehicle', 'allVehicles'
        ));
    }

    /**
     * Log a new refuel. Vehicle/driver resolution is identical to
     * TripLogController::store(): a DRIVER can only ever log against their
     * own assigned vehicle; a SUPER ADMINISTRATOR may log for any vehicle.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);

        if (! in_array($role, [self::ROLE_SUPER_ADMIN, self::ROLE_DRIVER], true)) {
            return response()->json(['success' => false, 'message' => 'Your account type is not permitted to log refuels.'], 403);
        }

        $rules = [
            'refuel_date'      => ['required', 'date'],
            'liters'           => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'total_cost'       => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'odometer_reading' => ['required', 'integer', 'min:0'],
            'receipt'          => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
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
            $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
            $validated['driver_id'] = $vehicle->assigned_driver_id;
        }

        $validated['logged_by'] = $user->id;

        if ($request->hasFile('receipt')) {
            $validated['receipt_path'] = $request->file('receipt')->store('fuel_logs/receipts', 'public');
        }
        unset($validated['receipt']);

        $log = FuelLog::create($validated);

        ActivityLog::record(
            'created',
            'Fuel Log',
            'Logged a refuel for [' . strtoupper($vehicle->plate_number) . '] (' . number_format((float) $validated['liters'], 2) . ' L, ₱' . number_format((float) $validated['total_cost'], 2) . ').',
            $log,
            ['after' => $validated]
        );

        return response()->json([
            'success' => true,
            'message' => 'Refuel logged for [' . strtoupper($vehicle->plate_number) . '].',
        ]);
    }

    /**
     * Serves an uploaded receipt directly from the private storage disk —
     * same rationale as VehicleController/MaintenanceController::viewDocument
     * (bypasses the public/storage symlink, unreliable on Windows/WAMP, and
     * keeps it behind auth).
     */
    public function viewDocument(string $path)
    {
        if (! str_starts_with($path, 'fuel_logs/') || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }
}
