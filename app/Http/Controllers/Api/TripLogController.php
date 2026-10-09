<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TripLog;
use App\Models\Vehicle;
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
        unset($validated['round_trip'], $validated['return_departure_time'], $validated['return_arrival_time'], $validated['return_odometer_end'], $validated['vehicle_id']);

        $validated['vehicle_id'] = $vehicle->id;
        $validated['driver_id'] = $driver->id;
        $validated['logged_by'] = $request->user()->id;

        if ($isRoundTrip) {
            $validated['round_trip_group'] = (string) Str::uuid();
            $validated['leg'] = 'outbound';
        }

        $trip = TripLog::create($validated);

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
        ];
    }
}
