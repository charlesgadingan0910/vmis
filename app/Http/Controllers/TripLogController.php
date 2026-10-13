<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Station;
use App\Models\TripLog;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Support\GeoDistance;
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
 * Trip logs are never edited once created (see store()) — still the same
 * audit-trail treatment as ActivityLog. The one exception is destroy(): a
 * SUPER ADMINISTRATOR alone (no other role, however broad its visibility
 * otherwise) may permanently remove a trip log, for genuinely mistaken or
 * duplicate entries. Every deletion writes a full snapshot to the Activity
 * Log first, so even this stays auditable after the fact.
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

    /**
     * Excludes a round trip's return leg from a TripLog query — a round
     * trip is still two physical rows (see TripLog's own docblock: one
     * per leg, sharing round_trip_group), but the Trip History table
     * shows ONE row per round trip, built from the outbound leg plus its
     * roundTripPartner() (see buildTripRow()). Applied to every count/
     * list query in adminAjaxList()/adminIndex() so totals, pagination
     * and "Logged Today/This Month" all agree with what's actually shown
     * — a round trip counts once, not twice.
     */
    protected function excludingReturnLegs($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('leg')->orWhere('leg', 'outbound');
        });
    }

    protected function adminAjaxList(Request $request, $user, string $role): JsonResponse
    {
        $query = TripLog::with(['vehicle', 'driver', 'loggedBy'])->orderByDesc('trip_date')->orderByDesc('id');
        $this->scopeToVisibleVehicles($query, $user, $role, viaRelation: true);
        $this->excludingReturnLegs($query);

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
        $this->excludingReturnLegs($totalScoped);
        $totalRecords = $totalScoped->count();
        $filteredRecords = (clone $query)->count();

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $trips = $query->skip($start)->take($length)->get();

        // Computed once per request, not per row — see buildTripRow()'s
        // $canDelete param.
        $canDelete = $role === self::ROLE_SUPER_ADMIN;

        $data = [];
        foreach ($trips as $trip) {
            $data[] = $this->buildTripRow($trip, $canDelete);
        }

        return response()->json([
            'draw'            => intval($request->input('draw')),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }

    /**
     * Builds one Trip History table row from $trip. For a one-way trip
     * this is just $trip; for a round trip (leg === 'outbound' — the
     * query this is called from never hands it a 'return' row directly,
     * see excludingReturnLegs()) it's $trip combined with its
     * roundTripPartner() so the admin sees the whole excursion — both
     * legs' times and a continuous odometer range — as a single row,
     * rather than the two separate rows this page used to show. The
     * return leg is still its own permanent, append-only TripLog record
     * underneath (TripLogController::store() / Api\TripLogController::
     * store() haven't changed); only how it's *displayed* has.
     *
     * $canDelete gates the new Delete button in actions_html — computed
     * once by the caller (adminAjaxList()) rather than re-checking the
     * role on every row.
     */
    protected function buildTripRow(TripLog $trip, bool $canDelete = false): array
    {
        $returnLeg = $trip->leg === 'outbound' ? $trip->roundTripPartner() : null;
        $isRoundTrip = $returnLeg !== null;

        $vehicle = $trip->vehicle;
        $vehicleHtml = $vehicle
            ? '<span class="plate-badge">' . e(strtoupper($vehicle->plate_number)) . '</span>' .
              '<div class="vehicle-sub-info mt-1">' . e(trim($vehicle->make . ' ' . $vehicle->model)) . '</div>'
            : '<span class="text-muted">Vehicle removed</span>';

        $driverName = $trip->driver ? trim($trip->driver->firstname . ' ' . $trip->driver->lastname) : '—';
        $driverHtml = '<div class="vehicle-sub-info">' . e($driverName) . '</div>';

        $fmtTime = fn ($raw) => $raw ? date('h:i A', strtotime($raw)) : '—';

        $tripHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . $trip->trip_date->format('M d, Y') . '</div>';
        if ($isRoundTrip) {
            $tripHtml .= '<div class="vehicle-sub-info">Out ' . $fmtTime($trip->departure_time) . ' to ' . $fmtTime($trip->arrival_time) . '</div>';
            $tripHtml .= '<div class="vehicle-sub-info">Back ' . $fmtTime($returnLeg->departure_time) . ' to ' . $fmtTime($returnLeg->arrival_time) . '</div>';
        } elseif ($trip->departure_time || $trip->arrival_time) {
            $tripHtml .= '<div class="vehicle-sub-info">' . $fmtTime($trip->departure_time) . ' to ' . $fmtTime($trip->arrival_time) . '</div>';
        }

        $routeHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . e($trip->origin) . ' ' . ($isRoundTrip ? '&#8644;' : '&rarr;') . ' ' . e($trip->destination);
        if ($isRoundTrip) {
            $routeHtml .= '<span class="round-trip-badge" title="Logged as two linked trip records — outbound and return"><i class="fas fa-exchange-alt"></i> Round Trip</span>';
        }
        $routeHtml .= '</div>';
        if ($trip->purpose) {
            $routeHtml .= '<div class="vehicle-sub-info">' . e(Str::limit($trip->purpose, 60)) . '</div>';
        }

        // Continuous across both legs — the return leg's own
        // odometer_start is just the outbound leg's odometer_end (see
        // TripLogController::store()'s round-trip block), so showing
        // outbound-start → return-end reads as one unbroken mileage
        // range for the whole excursion, same as the driver experiences it.
        $odoStart = $trip->odometer_start;
        $odoEnd = $isRoundTrip ? $returnLeg->odometer_end : $trip->odometer_end;
        $odometerHtml = '<span class="text-muted">—</span>';
        if ($odoStart !== null || $odoEnd !== null) {
            $odometerHtml = number_format((int) $odoStart) . ' &rarr; ' . number_format((int) $odoEnd);
            if ($odoStart !== null && $odoEnd !== null && $odoEnd >= $odoStart) {
                $odometerHtml .= '<div class="vehicle-sub-info mt-1">' . number_format($odoEnd - $odoStart) . ' km traveled</div>';
            }
        }

        $loggedHtml = '<div class="vehicle-sub-info">' . e(optional($trip->loggedBy)->fullname ?? 'Unknown user') . '</div>' .
                      '<div class="vehicle-sub-info">' . $trip->created_at->format('M d, Y h:i A') . '</div>';

        // GPS column: only trips logged live from the driver app's Start
        // Trip flow carry a route. For a round trip, the whole excursion's
        // GPS trail lives on the outbound leg only — a return leg never
        // carries its own route/distance/speed (see TripLogController::
        // store()'s round-trip block) — so $trip (outbound, or the plain
        // one-way trip) is always the right row to read this from. The
        // "View Route" link embeds the raw point array as a data
        // attribute rather than hitting a new endpoint, since DataTables
        // already paginates these rows server-side — see the Leaflet
        // handler at the bottom of this view.
        if ($trip->hasGpsRoute()) {
            $gpsHtml = '<span class="gps-tracked-badge" title="Captured live by the driver app"><i class="fas fa-satellite-dish"></i> GPS</span>';
            if ($trip->distance_km !== null) {
                $gpsHtml .= '<div class="vehicle-sub-info mt-1">' . number_format((float) $trip->distance_km, 2) . ' km</div>';
            }
            if ($trip->hasSpeedData()) {
                $gpsHtml .= '<div class="speed-chip mt-1" title="Average / top speed during this trip"><i class="fas fa-tachometer-alt"></i> ' .
                    ($trip->avg_speed_kmh !== null ? number_format((float) $trip->avg_speed_kmh, 1) . ' avg' : '—');
                if ($trip->max_speed_kmh !== null) {
                    $gpsHtml .= ' · ' . number_format((float) $trip->max_speed_kmh, 1) . ' max';
                }
                $gpsHtml .= ' km/h</div>';
            }
            $gpsHtml .= '<button type="button" class="btn btn-link btn-sm p-0 mt-1 view-route-btn" data-route="' . e(json_encode($trip->route_points)) . '" data-trip-label="' . e($trip->origin . ' → ' . $trip->destination) .
                '" data-distance="' . e($trip->distance_km ?? '') .
                '" data-avg-speed="' . e($trip->avg_speed_kmh ?? '') .
                '" data-max-speed="' . e($trip->max_speed_kmh ?? '') . '">View Route</button>';
        } else {
            $gpsHtml = '<span class="text-muted">—</span>';
        }

        // Delete is the one write action a SUPER ADMINISTRATOR alone gets on
        // an otherwise permanent record — see destroy() and the class
        // docblock. Every other role sees a dash, same empty-state style
        // used elsewhere in this row.
        $actionsHtml = $canDelete
            ? '<button type="button" class="btn btn-sm btn-light border delete-trip-btn" data-id="' . $trip->id .
                '" data-round-trip="' . ($isRoundTrip ? '1' : '0') .
                '" title="Delete this trip log (Super Administrator only)"><i class="fas fa-trash text-danger"></i></button>'
            : '<span class="text-muted">—</span>';

        return [
            'vehicle_html'  => $vehicleHtml,
            'driver_html'   => $driverHtml,
            'trip_html'     => $tripHtml,
            'route_html'    => $routeHtml,
            'odometer_html' => $odometerHtml,
            'gps_html'      => $gpsHtml,
            'logged_html'   => $loggedHtml,
            'actions_html'  => $actionsHtml,
        ];
    }

    /**
     * Permanently removes a trip log — the one capability intentionally
     * withheld everywhere else in this controller (see the class docblock:
     * trip logs are otherwise create + view only, like Activity Logs).
     * Restricted to SUPER ADMINISTRATOR alone, not the broader
     * BROAD_VISIBILITY_ROLES/ADMIN_VIEW_ROLES used for reading, because this
     * is the one action that can erase part of the permanent record rather
     * than just view it.
     *
     * $tripLog may be either leg of a round trip — whichever one the
     * admin's Delete button was on. Both legs are removed together so the
     * pair never ends up with an orphaned partner still on file, which
     * buildTripRow()'s merge display assumes never happens. A full
     * snapshot of whatever is removed is written to the Activity Log
     * first, so even this stays auditable after the fact.
     *
     * Parameter is named $tripLog (not $trip) so Laravel's implicit route
     * model binding resolves it against Route::resource('trip-logs', ...)'s
     * {trip_log} wildcard — see Illuminate\Routing\ImplicitRouteBinding's
     * Str::snake() fallback, same convention VehicleTypeController::
     * destroy(VehicleType $vehicleType) already relies on for 'vehicle-types'.
     */
    public function destroy(TripLog $tripLog): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);

        if ($role !== self::ROLE_SUPER_ADMIN) {
            abort(403, 'Only a Super Administrator may delete a trip log.');
        }

        $partner = $tripLog->roundTripPartner();
        $isRoundTrip = $partner !== null;
        $vehicle = $tripLog->vehicle;
        $plateLabel = $vehicle ? strtoupper($vehicle->plate_number) : 'unknown vehicle';

        $snapshot = $tripLog->toArray();
        $partnerSnapshot = $partner?->toArray();

        $tripLog->delete();
        $partner?->delete();

        ActivityLog::record(
            'deleted',
            'Trip Log',
            $isRoundTrip
                ? 'Deleted a round trip log for [' . $plateLabel . '] (' . $tripLog->origin . ' ⇄ ' . $tripLog->destination . ') — both legs removed.'
                : 'Deleted a trip log for [' . $plateLabel . '] (' . $tripLog->origin . ' to ' . $tripLog->destination . ').',
            $tripLog,
            $isRoundTrip ? ['before' => $snapshot, 'before_return_leg' => $partnerSnapshot] : ['before' => $snapshot]
        );

        return response()->json([
            'success' => true,
            'message' => $isRoundTrip ? 'Round trip log deleted — both legs removed.' : 'Trip log deleted.',
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
        // A round trip is two TripLog rows but one trip on screen (see
        // buildTripRow()) — excluded here too so these counts agree with
        // what the table actually shows a round trip as: one.
        $this->excludingReturnLegs($tripScope);

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

            // Round trip: Destination back to Origin, logged as its own
            // second leg in the same submit — see the block below store().
            'round_trip'            => ['nullable', 'boolean'],
            'return_departure_time' => ['nullable', 'date_format:H:i'],
            'return_arrival_time'   => ['nullable', 'date_format:H:i', 'after_or_equal:return_departure_time'],
            'return_odometer_end'   => ['nullable', 'integer', 'min:0', function ($attribute, $value, $fail) use ($request) {
                $odometerEnd = $request->input('odometer_end');
                if ($value !== null && $odometerEnd !== null && (int) $value < (int) $odometerEnd) {
                    $fail('Return odometer (end) can\'t be less than the outbound odometer (end) — mileage only goes up.');
                }
            }],

            // Real-time GPS capture from the driver app's live-tracked trip
            // flow — all optional, since a manually-typed trip (desktop or
            // app) never sends any of these. See GeoDistance::totalKm() for
            // how distance_km is actually derived once route_points is
            // present (it overrides whatever the client sent).
            'start_latitude'           => ['nullable', 'numeric', 'between:-90,90'],
            'start_longitude'          => ['nullable', 'numeric', 'between:-180,180'],
            'end_latitude'             => ['nullable', 'numeric', 'between:-90,90'],
            'end_longitude'            => ['nullable', 'numeric', 'between:-180,180'],
            'distance_km'              => ['nullable', 'numeric', 'min:0'],
            'route_points'             => ['nullable', 'array', 'max:3000'],
            'route_points.*.lat'       => ['required_with:route_points', 'numeric', 'between:-90,90'],
            'route_points.*.lng'       => ['required_with:route_points', 'numeric', 'between:-180,180'],
            'route_points.*.recorded_at' => ['nullable', 'date'],
        ];

        if ($role === self::ROLE_SUPER_ADMIN) {
            $rules['vehicle_id'] = ['required', 'exists:vehicles,id'];
        }

        $validated = $request->validate($rules);
        $isRoundTrip = $request->boolean('round_trip');
        $returnLegInput = [
            'departure_time' => $validated['return_departure_time'] ?? null,
            'arrival_time'   => $validated['return_arrival_time'] ?? null,
            'odometer_end'   => $validated['return_odometer_end'] ?? null,
        ];
        unset($validated['round_trip'], $validated['return_departure_time'], $validated['return_arrival_time'], $validated['return_odometer_end']);

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

        if ($isRoundTrip) {
            $validated['round_trip_group'] = (string) Str::uuid();
            $validated['leg'] = 'outbound';
        }

        // Authoritative distance: whenever a full route trail was sent,
        // it always wins over whatever (if anything) the client computed
        // on-device, so this can't be spoofed by editing the request.
        if (! empty($validated['route_points'])) {
            $validated['distance_km'] = GeoDistance::totalKm($validated['route_points']);
        }

        $trip = TripLog::create($validated);

        $returnTrip = null;
        if ($isRoundTrip) {
            // Retraces the outbound route in reverse. Mileage is continuous
            // across both legs, so the return leg's starting odometer picks
            // up exactly where the outbound leg's ending odometer left off —
            // the form never asks for it separately.
            $returnTrip = TripLog::create([
                'vehicle_id'        => $validated['vehicle_id'],
                'driver_id'         => $validated['driver_id'],
                'logged_by'         => $validated['logged_by'],
                'trip_date'         => $validated['trip_date'],
                'departure_time'    => $returnLegInput['departure_time'],
                'arrival_time'      => $returnLegInput['arrival_time'],
                'origin'            => $validated['destination'],
                'destination'       => $validated['origin'],
                'purpose'           => $validated['purpose'] ?? null,
                'odometer_start'    => $validated['odometer_end'] ?? null,
                'odometer_end'      => $returnLegInput['odometer_end'],
                'passengers'        => $validated['passengers'] ?? null,
                'remarks'           => $validated['remarks'] ?? null,
                'round_trip_group'  => $validated['round_trip_group'],
                'leg'               => 'return',
            ]);
        }

        ActivityLog::record(
            'created',
            'Trip Log',
            $isRoundTrip
                ? 'Logged a round trip for [' . strtoupper($vehicle->plate_number) . '] (' . $validated['origin'] . ' ⇄ ' . $validated['destination'] . ').'
                : 'Logged a trip for [' . strtoupper($vehicle->plate_number) . '] (' . $validated['origin'] . ' to ' . $validated['destination'] . ').',
            $trip,
            ['after' => $validated]
        );

        return response()->json([
            'success' => true,
            'message' => $isRoundTrip
                ? 'Round trip logged for [' . strtoupper($vehicle->plate_number) . '] — both legs saved.'
                : 'Trip logged for [' . strtoupper($vehicle->plate_number) . '].',
        ]);
    }
}
