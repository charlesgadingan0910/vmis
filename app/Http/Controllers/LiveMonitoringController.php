<?php

namespace App\Http\Controllers;

use App\Models\ActiveTripSession;
use App\Models\Station;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin "Live Monitoring" map — every vehicle currently mid-trip,
 * updated from Api\ActiveTripController's pings (see that class's own
 * docblock for the full ping/end lifecycle). Read-only: there is nothing
 * to create/edit/delete here, only ActiveTripSession rows the driver app
 * itself keeps current.
 *
 * DRIVER accounts can't reach this at all — they only ever need to see
 * their own trip, which the mobile app already shows live while tracking.
 * Visibility for everyone else mirrors TripLogController exactly: SUPER
 * ADMINISTRATOR/ADMINISTRATOR/VIEWER see the whole fleet, UNIT
 * ADMINISTRATOR is scoped to their unit, STATION ADMINISTRATOR to their
 * station.
 */
class LiveMonitoringController extends Controller
{
    protected const ROLE_SUPER_ADMIN = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER = 'VIEWER';
    protected const ROLE_DRIVER = 'DRIVER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Same shape as TripLogController::scopeToVisibleVehicles() — an
     * ActiveTripSession only ever owns a vehicle via relation, so this is
     * always the whereHas('vehicle', ...) branch.
     */
    protected function scopeToVisibleVehicles($query, $user, string $role): void
    {
        if (in_array($role, self::BROAD_VISIBILITY_ROLES, true)) {
            return;
        }

        $query->whereHas('vehicle', function ($q) use ($user, $role) {
            if ($role === self::ROLE_UNIT_ADMIN) {
                $q->where('unit_id', $user->unit_id);
            } elseif ($role === self::ROLE_STATION_ADMIN) {
                $q->where('station_id', $user->station_id);
            }
        });
    }

    public function index()
    {
        $user = auth()->user();
        $role = $this->role($user);

        if ($role === self::ROLE_DRIVER) {
            abort(403, 'Your account type does not have access to Live Monitoring.');
        }

        $hasBroadVisibility = in_array($role, self::BROAD_VISIBILITY_ROLES, true);

        // Same "cannot write" set GeofenceController itself enforces —
        // VIEWER sees the Geofences panel read-only, everyone else here
        // (every non-DRIVER role that reaches this page) can draw zones,
        // scoped to their own unit/station for a Unit/Station
        // Administrator (see GeofenceController::resolveScope()).
        $canManageGeofences = $role !== self::ROLE_VIEWER;

        $units = $hasBroadVisibility
            ? Unit::orderBy('unit_name')->get()
            : Unit::where('id', $user->unit_id)->orderBy('unit_name')->get();

        $stations = $hasBroadVisibility
            ? Station::orderBy('station_name')->get()
            : ($role === self::ROLE_UNIT_ADMIN
                ? Station::where('unit_id', $user->unit_id)->orderBy('station_name')->get()
                : Station::where('id', $user->station_id)->orderBy('station_name')->get());

        return view('live_monitoring.index', compact('units', 'stations', 'canManageGeofences', 'hasBroadVisibility'));
    }

    public function activeTrips(Request $request): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);

        if ($role === self::ROLE_DRIVER) {
            abort(403, 'Your account type does not have access to Live Monitoring.');
        }

        // Lazy cleanup, same pattern as FleetAlertSyncService's dedupe —
        // no scheduled cron job needed. A session gone quiet this long is
        // almost certainly a crashed app or a trip the driver forgot to
        // end, not a genuinely-still-active one, so it's purged here
        // rather than left to clutter the map indefinitely.
        ActiveTripSession::where('last_ping_at', '<', now()->subMinutes(30))->delete();

        $query = ActiveTripSession::with(['vehicle', 'driver'])->orderByDesc('started_at');
        $this->scopeToVisibleVehicles($query, $user, $role);
        $sessions = $query->get();

        $data = $sessions->map(function (ActiveTripSession $s) {
            return [
                'vehicle_id' => $s->vehicle_id,
                'plate_number' => $s->vehicle ? strtoupper($s->vehicle->plate_number) : null,
                'vehicle_label' => $s->vehicle ? trim($s->vehicle->make . ' ' . $s->vehicle->model) : null,
                'driver_name' => $s->driver ? trim($s->driver->firstname . ' ' . $s->driver->lastname) : 'Unknown driver',
                'started_at' => optional($s->started_at)->toIso8601String(),
                'point_count' => $s->point_count,
                'distance_km' => $s->distance_km !== null ? (float) $s->distance_km : 0,
                'latitude' => $s->latest_latitude !== null ? (float) $s->latest_latitude : null,
                'longitude' => $s->latest_longitude !== null ? (float) $s->latest_longitude : null,
                'last_ping_at' => optional($s->last_ping_at)->toIso8601String(),
                'stale' => $s->isStale(),
                'route_points' => $s->route_points ?? [],
            ];
        })->filter(fn ($t) => $t['latitude'] !== null && $t['longitude'] !== null)->values();

        return response()->json(['success' => true, 'trips' => $data]);
    }
}
