<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TripLog;
use App\Models\TripRegistration;
use App\Models\Vehicle;
use App\Support\GeoDistance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Trip Logs for the VMIS mobile app.
 *
 * A DRIVER logs trips for their own assigned vehicle and sees only their
 * own history — validation and vehicle/driver resolution deliberately
 * mirror \App\Http\Controllers\TripLogController::store()'s DRIVER branch
 * exactly, including the round-trip (paired return-leg) option, so a trip
 * logged from the app is indistinguishable from one logged on the
 * desktop's driver view.
 *
 * A VIEWER never logs a trip (read-only, matching the desktop's
 * `ROLE_VIEWER` exclusion from TripLogController::store()) but sees every
 * vehicle's trips fleet-wide, optionally narrowed to one vehicle.
 */
class TripLogController extends Controller
{
    protected function isViewer(Request $request): bool
    {
        return strtoupper(trim((string) $request->user()->account_type)) === 'VIEWER';
    }

    public function index(Request $request): JsonResponse
    {
        if ($this->isViewer($request)) {
            return $this->viewerIndex($request);
        }

        $driver = $this->resolveDriver($request);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Your account is not linked to a driver profile yet. Contact your administrator.'], 403);
        }

        $trips = TripLog::with('vehicle')
            ->where('driver_id', $driver->id)
            ->orderByDesc('trip_date')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'current_page' => $trips->currentPage(),
            'last_page' => $trips->lastPage(),
            'total' => $trips->total(),
            'trips' => collect($trips->items())->map(fn (TripLog $trip) => $this->tripPayload($trip)),
        ]);
    }

    protected function viewerIndex(Request $request): JsonResponse
    {
        $query = TripLog::with('vehicle')->orderByDesc('trip_date')->orderByDesc('id');

        if ($vehicleId = $request->integer('vehicle_id')) {
            $query->where('vehicle_id', $vehicleId);
        }

        $trips = $query->paginate(20);

        return response()->json([
            'success' => true,
            'current_page' => $trips->currentPage(),
            'last_page' => $trips->lastPage(),
            'total' => $trips->total(),
            'trips' => collect($trips->items())->map(fn (TripLog $trip) => $this->tripPayload($trip)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->isViewer($request)) {
            return response()->json(['success' => false, 'message' => 'Your account type is not permitted to log trips.'], 403);
        }

        $driver = $this->resolveDriver($request);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Your account is not linked to a driver profile yet. Contact your administrator.'], 403);
        }

        $validated = $request->validate([
            'trip_date' => ['required', 'date'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'arrival_time' => ['nullable', 'date_format:H:i', 'after_or_equal:departure_time'],
            'origin' => ['required', 'string', 'max:150'],
            'destination' => ['required', 'string', 'max:150'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'odometer_start' => ['nullable', 'integer', 'min:0'],
            'odometer_end' => ['nullable', 'integer', 'min:0', 'gte:odometer_start'],
            'passengers' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'vehicle_id' => ['nullable', 'integer'],

            // Round trip: destination back to origin, logged as its own
            // second leg in the same submit — mirrors the desktop's
            // TripLogController::store() exactly.
            'round_trip' => ['nullable', 'boolean'],
            'return_departure_time' => ['nullable', 'date_format:H:i'],
            'return_arrival_time' => ['nullable', 'date_format:H:i', 'after_or_equal:return_departure_time'],
            'return_odometer_end' => ['nullable', 'integer', 'min:0', function ($attribute, $value, $fail) use ($request) {
                $odometerEnd = $request->input('odometer_end');
                if ($value !== null && $odometerEnd !== null && (int) $value < (int) $odometerEnd) {
                    $fail('Return odometer (end) can\'t be less than the outbound odometer (end) — mileage only goes up.');
                }
            }],

            // Real-time GPS capture from the app's live-tracked "Start
            // Trip" flow (TripTrackingService) — all optional, so a trip
            // logged the old manual way never sends any of these. See
            // GeoDistance::totalKm() below: whenever route_points is sent,
            // the server recomputes distance_km from it rather than
            // trusting whatever the client calculated on-device.
            'start_latitude'           => ['nullable', 'numeric', 'between:-90,90'],
            'start_longitude'          => ['nullable', 'numeric', 'between:-180,180'],
            'end_latitude'             => ['nullable', 'numeric', 'between:-90,90'],
            'end_longitude'            => ['nullable', 'numeric', 'between:-180,180'],
            'distance_km'              => ['nullable', 'numeric', 'min:0'],
            // Speed summary from the live-tracked "Start Trip" flow
            // (TripTrackingService) — like distance_km, only ever sent for
            // a GPS-tracked trip and stored on the primary/outbound leg's
            // row only, never duplicated onto an auto-created return leg.
            'avg_speed_kmh'            => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'max_speed_kmh'            => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'route_points'             => ['nullable', 'array', 'max:3000'],
            'route_points.*.lat'       => ['required_with:route_points', 'numeric', 'between:-90,90'],
            'route_points.*.lng'       => ['required_with:route_points', 'numeric', 'between:-180,180'],
            'route_points.*.recorded_at' => ['nullable', 'date'],

            // Set only when this trip started from an approved
            // TripRegistration (see Api\TripRegistrationController::start())
            // — a routine instant-start trip never sends this at all.
            'trip_registration_id' => ['nullable', 'integer', 'exists:trip_registrations,id'],
        ]);

        $vehicle = Vehicle::where('assigned_driver_id', $driver->id)
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('id', $request->integer('vehicle_id')))
            ->first();

        if (! $vehicle) {
            return response()->json(['success' => false, 'message' => 'No vehicle is currently assigned to you. Contact your administrator.'], 403);
        }

        $isRoundTrip = (bool) ($validated['round_trip'] ?? false);
        $returnLegInput = [
            'departure_time' => $validated['return_departure_time'] ?? null,
            'arrival_time' => $validated['return_arrival_time'] ?? null,
            'odometer_end' => $validated['return_odometer_end'] ?? null,
        ];
        $registrationId = $validated['trip_registration_id'] ?? null;
        unset(
            $validated['round_trip'], $validated['return_departure_time'], $validated['return_arrival_time'],
            $validated['return_odometer_end'], $validated['vehicle_id'], $validated['trip_registration_id']
        );

        // A registration marked round-trip by the admin (TripRegistration
        // ::round_trip) is enforced here too, not just pre-checked/locked
        // client-side — the app can't bypass it just by sending round_trip
        // false for a trip it started from that registration.
        if ($registrationId) {
            $sourceRegistration = TripRegistration::find($registrationId);
            if ($sourceRegistration && $sourceRegistration->isRoundTrip()) {
                $isRoundTrip = true;
            }
        }

        $validated['vehicle_id'] = $vehicle->id;
        $validated['driver_id'] = $driver->id;
        $validated['logged_by'] = $request->user()->id;

        if ($isRoundTrip) {
            $validated['round_trip_group'] = (string) Str::uuid();
            $validated['leg'] = 'outbound';
        }

        // Authoritative distance: a full route trail always overrides
        // whatever (if anything) the app computed on-device, so it can't
        // be spoofed by editing the request.
        if (! empty($validated['route_points'])) {
            $validated['distance_km'] = GeoDistance::totalKm($validated['route_points']);
        }

        $trip = TripLog::create($validated);

        // Close out the TripRegistration this trip started from, if any —
        // only ever one this driver (or their assigned assistant) actually
        // started (see Api\TripRegistrationController::start()), never
        // trusted blindly off a client-sent id.
        if ($registrationId) {
            $registration = TripRegistration::where('id', $registrationId)
                ->where('status', 'active')
                ->where(function ($q) use ($driver) {
                    $q->where('driver_id', $driver->id)->orWhere('assistant_driver_id', $driver->id);
                })
                ->first();

            if ($registration) {
                $registration->update(['status' => 'completed', 'trip_log_id' => $trip->id]);
            }
        }

        $returnTrip = null;
        if ($isRoundTrip) {
            // Mileage is continuous across both legs — the return leg's
            // starting odometer picks up exactly where the outbound leg's
            // ending odometer left off, the app never asks for it separately.
            $returnTrip = TripLog::create([
                'vehicle_id' => $validated['vehicle_id'],
                'driver_id' => $validated['driver_id'],
                'logged_by' => $validated['logged_by'],
                'trip_date' => $validated['trip_date'],
                'departure_time' => $returnLegInput['departure_time'],
                'arrival_time' => $returnLegInput['arrival_time'],
                'origin' => $validated['destination'],
                'destination' => $validated['origin'],
                'purpose' => $validated['purpose'] ?? null,
                'odometer_start' => $validated['odometer_end'] ?? null,
                'odometer_end' => $returnLegInput['odometer_end'],
                'passengers' => $validated['passengers'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'round_trip_group' => $validated['round_trip_group'],
                'leg' => 'return',
            ]);
        }

        // Mileage-based PMS trigger — see the desktop TripLogController::
        // store()'s identical hook for why this only ever moves the
        // vehicle's running odometer_km forward, never backward.
        $vehicle->bumpOdometer($returnTrip?->odometer_end ?? $trip->odometer_end);

        ActivityLog::record(
            'created',
            'Trip Log',
            ($isRoundTrip
                ? 'Logged a round trip for [' . strtoupper($vehicle->plate_number) . '] (' . $validated['origin'] . ' ⇄ ' . $validated['destination'] . ')'
                : 'Logged a trip for [' . strtoupper($vehicle->plate_number) . '] (' . $validated['origin'] . ' to ' . $validated['destination'] . ')'
            ) . ' via mobile app.',
            $trip,
            ['after' => $validated]
        );

        return response()->json([
            'success' => true,
            'message' => $isRoundTrip
                ? 'Round trip logged for [' . strtoupper($vehicle->plate_number) . '] — both legs saved.'
                : 'Trip logged for [' . strtoupper($vehicle->plate_number) . '].',
            'trip' => $this->tripPayload($trip->fresh('vehicle')),
        ], 201);
    }

    protected function resolveDriver(Request $request): ?Driver
    {
        $user = $request->user();

        return $user->driver_id ? Driver::find($user->driver_id) : null;
    }

    protected function tripPayload(TripLog $trip): array
    {
        return [
            'id' => $trip->id,
            'vehicle_plate_number' => $trip->vehicle ? strtoupper($trip->vehicle->plate_number) : null,
            'trip_date' => optional($trip->trip_date)->toDateString(),
            'departure_time' => $trip->departure_time,
            'arrival_time' => $trip->arrival_time,
            'origin' => $trip->origin,
            'destination' => $trip->destination,
            'purpose' => $trip->purpose,
            'odometer_start' => $trip->odometer_start,
            'odometer_end' => $trip->odometer_end,
            'passengers' => $trip->passengers,
            'remarks' => $trip->remarks,
            'leg' => $trip->leg,
            'logged_at' => optional($trip->created_at)->toIso8601String(),

            // GPS summary — only trips logged through the live-tracked
            // Start Trip flow carry any of this. The full route_points
            // trail isn't sent here (it's only used server-side to compute
            // distance_km); the app has no map view to draw it in yet.
            'has_gps_route' => $trip->hasGpsRoute(),
            'distance_km' => $trip->distance_km !== null ? (float) $trip->distance_km : null,
            'avg_speed_kmh' => $trip->avg_speed_kmh !== null ? (float) $trip->avg_speed_kmh : null,
            'max_speed_kmh' => $trip->max_speed_kmh !== null ? (float) $trip->max_speed_kmh : null,
            'start_latitude' => $trip->start_latitude !== null ? (float) $trip->start_latitude : null,
            'start_longitude' => $trip->start_longitude !== null ? (float) $trip->start_longitude : null,
            'end_latitude' => $trip->end_latitude !== null ? (float) $trip->end_latitude : null,
            'end_longitude' => $trip->end_longitude !== null ? (float) $trip->end_longitude : null,
        ];
    }
}
