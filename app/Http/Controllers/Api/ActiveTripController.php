<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActiveTripSession;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\GeofenceDetectionService;
use App\Support\GeoDistance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Powers the admin Live Monitoring map — NOT the permanent trip-log
 * record (see TripLogController for that). The driver app calls ping()
 * roughly every 10s while a GPS trip is active (TripTrackingService on
 * the Flutter side), giving admins a near-real-time "where is this
 * vehicle right now" view. Once the driver taps End Trip, the full
 * authoritative route is submitted the normal way to
 * TripLogController::store(), and end() here just deletes this live
 * session row so the pin disappears from the map immediately rather than
 * sitting there until it goes stale.
 *
 * DRIVER-only, same as TripLogController::store() — a VIEWER account
 * never has a trip of its own to track.
 */
class ActiveTripController extends Controller
{
    // How many of the most recent points a live session keeps — enough for
    // a meaningful "recent breadcrumb" on the map without the row growing
    // unbounded over a multi-hour duty tour. The full, uncapped route only
    // ever lands in trip_logs once the trip is actually logged.
    protected const MAX_ROUTE_POINTS = 500;

    /**
     * Resolves the calling driver's currently-assigned vehicle exactly the
     * way TripLogController::store() does — never trusts a client-sent
     * vehicle_id beyond picking among the driver's own vehicle(s).
     */
    protected function resolveVehicle(Request $request): ?array
    {
        if (strtoupper(trim((string) $request->user()->account_type)) !== 'DRIVER') {
            return null;
        }

        $driver = $request->user()->driver_id ? Driver::find($request->user()->driver_id) : null;
        if (! $driver) {
            return null;
        }

        $vehicle = Vehicle::assignedToDriver($driver->id)
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('id', $request->integer('vehicle_id')))
            ->first();

        return $vehicle ? ['vehicle' => $vehicle, 'driver' => $driver] : null;
    }

    public function ping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'vehicle_id' => ['nullable', 'integer'],
        ]);

        $resolved = $this->resolveVehicle($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'message' => 'No vehicle is currently assigned to you to track.'], 403);
        }

        $vehicle = $resolved['vehicle'];
        $driver = $resolved['driver'];
        $now = now();

        $session = ActiveTripSession::where('vehicle_id', $vehicle->id)->first();

        $previousPoint = ($session && $session->latest_latitude !== null && $session->latest_longitude !== null)
            ? ['lat' => (float) $session->latest_latitude, 'lng' => (float) $session->latest_longitude]
            : null;

        // Incremental distance: just the hop from the last known fix to
        // this one, added to the running total — cheap, and avoids
        // re-summing the whole (capped) trail on every single ping.
        $distanceDelta = $previousPoint
            ? GeoDistance::totalKm([$previousPoint, ['lat' => $validated['latitude'], 'lng' => $validated['longitude']]])
            : 0.0;

        // Geofence enter/exit detection — stateless, just compares this
        // vehicle's previous fix against its new one (see
        // GeofenceDetectionService's docblock). Independent of the
        // ActiveTripSession update below, so it runs even though the
        // session row itself doesn't track zone membership.
        app(GeofenceDetectionService::class)->detect(
            $vehicle,
            $driver->id,
            $previousPoint,
            ['lat' => $validated['latitude'], 'lng' => $validated['longitude']]
        );

        $points = $session->route_points ?? [];
        $points[] = ['lat' => $validated['latitude'], 'lng' => $validated['longitude'], 'recorded_at' => $now->toIso8601String()];
        if (count($points) > self::MAX_ROUTE_POINTS) {
            $points = array_slice($points, -self::MAX_ROUTE_POINTS);
        }

        ActiveTripSession::updateOrCreate(
            ['vehicle_id' => $vehicle->id],
            [
                'driver_id' => $driver->id,
                'started_by' => $request->user()->id,
                'started_at' => $session->started_at ?? $now,
                'latest_latitude' => $validated['latitude'],
                'latest_longitude' => $validated['longitude'],
                'last_ping_at' => $now,
                'point_count' => ($session->point_count ?? 0) + 1,
                'distance_km' => (float) ($session->distance_km ?? 0) + $distanceDelta,
                'route_points' => $points,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Called once, right when the driver taps End Trip — removes the live
     * pin immediately instead of leaving it to the 30-minute stale purge.
     * Never errors just because there's nothing to end (app restarted
     * mid-trip, double-tap, etc.) — this is cleanup, not a state machine
     * the client needs to get perfectly right.
     */
    public function end(Request $request): JsonResponse
    {
        $resolved = $this->resolveVehicle($request);
        if ($resolved) {
            ActiveTripSession::where('vehicle_id', $resolved['vehicle']->id)->delete();
        }

        return response()->json(['success' => true]);
    }
}
