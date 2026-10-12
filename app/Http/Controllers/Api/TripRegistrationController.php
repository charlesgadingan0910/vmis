<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TripRegistration;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The driver-facing half of the Register → Assign Personnel → Approve
 * workflow — see the web TripRegistrationController, where registrations
 * are actually created, approved, rejected or cancelled; nothing here can
 * do any of that. A driver only ever sees registrations they're part of
 * (as the primary driver or the assigned assistant), and the only action
 * available is starting an already-approved one — which hands off to the
 * existing GPS tracking flow exactly like an instant/unregistered trip
 * does, just pre-filled from the registration and linked back to it (see
 * Api\TripLogController::store()'s trip_registration_id handling).
 */
class TripRegistrationController extends Controller
{
    protected function resolveDriver(Request $request): ?Driver
    {
        $user = $request->user();

        return $user->driver_id ? Driver::find($user->driver_id) : null;
    }

    public function index(Request $request): JsonResponse
    {
        $driver = $this->resolveDriver($request);
        if (! $driver) {
            return response()->json(['success' => true, 'registrations' => []]);
        }

        $registrations = TripRegistration::with(['vehicle', 'driver', 'assistantDriver'])
            ->where(function ($q) use ($driver) {
                $q->where('driver_id', $driver->id)->orWhere('assistant_driver_id', $driver->id);
            })
            ->whereIn('status', ['pending_approval', 'approved'])
            ->orderByRaw("FIELD(status, 'approved', 'pending_approval')")
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'registrations' => $registrations->map(fn (TripRegistration $r) => $this->payload($r, $driver)),
        ]);
    }

    public function start(Request $request, TripRegistration $tripRegistration): JsonResponse
    {
        $driver = $this->resolveDriver($request);
        if (! $driver || ! in_array($driver->id, [$tripRegistration->driver_id, $tripRegistration->assistant_driver_id], true)) {
            return response()->json(['success' => false, 'message' => 'This trip registration is not assigned to you.'], 403);
        }

        if (! $tripRegistration->isStartable()) {
            return response()->json(['success' => false, 'message' => "This trip isn't approved and ready to start yet."], 422);
        }

        $tripRegistration->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'registration' => $this->payload($tripRegistration->fresh(['vehicle', 'driver', 'assistantDriver']), $driver),
        ]);
    }

    /**
     * Self-service counterpart to start() above — for a driver responding
     * to a sudden/emergency call with no pre-existing TripRegistration to
     * start from, and no time to wait for one to be admin-created and
     * approved first (the web TripRegistrationController::store() still
     * handles the admin-initiated Emergency path exactly as before — this
     * is the second, driver-initiated path onto the same trip_type).
     * Creates its own TripRegistration and activates it in the same
     * step — registered_by/approved_by are the driver's own account
     * rather than an admin's, preserving the audit trail without anyone
     * needing to be online to sign off. origin/destination are
     * deliberately left null here (see the migration that made those
     * columns nullable) — there's no time to ask for them before leaving
     * — and get filled in once the trip ends instead, same as any other
     * trip (see trips_screen.dart's _GpsTripEndingForm).
     */
    public function startEmergency(Request $request): JsonResponse
    {
        $driver = $this->resolveDriver($request);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Your account is not linked to a driver profile yet. Contact your administrator.'], 403);
        }

        $validated = $request->validate([
            'vehicle_id' => ['nullable', 'integer'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ]);

        // Same resolution rule as every other driver-facing endpoint
        // (Api\ActiveTripController::resolveVehicle(), Api\TripLogController
        // ::store()) — never trusts a client-sent vehicle_id beyond picking
        // among the driver's own assigned vehicle(s).
        $vehicle = Vehicle::where('assigned_driver_id', $driver->id)
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('id', $validated['vehicle_id']))
            ->first();

        if (! $vehicle) {
            return response()->json(['success' => false, 'message' => 'No vehicle is currently assigned to you. Contact your administrator.'], 403);
        }

        $user = $request->user();

        $registration = TripRegistration::create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assistant_driver_id' => null,
            'trip_type' => 'emergency',
            'purpose' => $validated['purpose'] ?? null,
            'origin' => null,
            'destination' => null,
            // Skips the usual pending_approval/approved steps entirely —
            // declaring and starting are the same action for a
            // self-service emergency trip.
            'status' => 'active',
            'registered_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        ActivityLog::record(
            'created',
            'Trip Registration',
            'Driver self-declared an EMERGENCY trip for [' . strtoupper($vehicle->plate_number) . '] from the mobile app.',
            $registration,
            ['after' => $registration->toArray()]
        );

        return response()->json([
            'success' => true,
            'registration' => $this->payload($registration->fresh(['vehicle', 'driver', 'assistantDriver']), $driver),
        ], 201);
    }

    protected function payload(TripRegistration $r, Driver $viewingDriver): array
    {
        return [
            'id' => $r->id,
            'vehicle_id' => $r->vehicle_id,
            'vehicle_plate_number' => $r->vehicle ? strtoupper($r->vehicle->plate_number) : null,
            'trip_type' => $r->trip_type,
            'purpose' => $r->purpose,
            'origin' => $r->origin,
            'destination' => $r->destination,
            'scheduled_date' => optional($r->scheduled_date)->toDateString(),
            'scheduled_departure_time' => $r->scheduled_departure_time,
            'status' => $r->status,
            'is_assistant' => $r->assistant_driver_id === $viewingDriver->id,
            'driver_name' => $r->driver ? trim($r->driver->firstname . ' ' . $r->driver->lastname) : null,
            'assistant_name' => $r->assistantDriver ? trim($r->assistantDriver->firstname . ' ' . $r->assistantDriver->lastname) : null,
        ];
    }
}
