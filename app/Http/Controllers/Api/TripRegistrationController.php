<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\TripRegistration;
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
