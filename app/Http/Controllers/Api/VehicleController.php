<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only vehicle screen for the VMIS mobile app.
 *
 * A DRIVER sees whichever vehicle(s) are currently assigned to them — the
 * original behavior. A VIEWER instead gets every vehicle PRO5-wide, the
 * same broad visibility \App\Http\Controllers\TripLogController and its
 * siblings already give a VIEWER on the desktop site — this is a fleet-wide
 * read-only account, not tied to a single assigned vehicle. Either way the
 * same PMS/registration/condition signals shown on the desktop Dashboard
 * and Vehicle Inventory come back in plain fields the app can render
 * directly.
 */
class VehicleController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (strtoupper(trim((string) $user->account_type)) === 'VIEWER') {
            return $this->viewerShow($request);
        }

        $driver = $user->driver_id ? Driver::find($user->driver_id) : null;

        if (! $driver) {
            return response()->json([
                'success' => true,
                'vehicles' => [],
                'message' => 'Your account is not linked to a driver profile yet. Contact your administrator.',
            ]);
        }

        $vehicles = Vehicle::where('assigned_driver_id', $driver->id)
            ->with(['type', 'unit', 'station', 'latestMaintenanceRecord', 'latestFuelLog', 'latestRegistration'])
            ->orderBy('plate_number')
            ->get()
            ->map(fn (Vehicle $vehicle) => $this->vehiclePayload($vehicle));

        return response()->json([
            'success' => true,
            'vehicles' => $vehicles,
            'message' => $vehicles->isEmpty() ? 'No vehicle is currently assigned to you. Contact your administrator.' : null,
        ]);
    }

    /**
     * A VIEWER browses the whole fleet rather than one assigned vehicle —
     * optionally narrowed with a `search` query param (plate/make/model),
     * since a fleet-wide list is too long to scroll through unfiltered.
     */
    protected function viewerShow(Request $request): JsonResponse
    {
        $query = Vehicle::with(['type', 'unit', 'station', 'latestMaintenanceRecord', 'latestFuelLog', 'latestRegistration'])
            ->orderBy('plate_number');

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('plate_number', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        $vehicles = $query->get()->map(fn (Vehicle $vehicle) => $this->vehiclePayload($vehicle));

        return response()->json([
            'success' => true,
            'vehicles' => $vehicles,
            'message' => $vehicles->isEmpty() ? 'No vehicles found.' : null,
        ]);
    }

    protected function vehiclePayload(Vehicle $vehicle): array
    {
        $pmsDays = $vehicle->pmsDaysRemaining();
        $regDays = $vehicle->registrationDaysRemaining();

        return [
            'id' => $vehicle->id,
            'plate_number' => strtoupper($vehicle->plate_number),
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'year_model' => $vehicle->year_model,
            'color' => $vehicle->color,
            'type' => optional($vehicle->type)->name,
            'unit' => optional($vehicle->unit)->unit_name,
            'station' => optional($vehicle->station)->station_name,
            'status' => $vehicle->status,
            'odometer_km' => $vehicle->odometer_km,
            'pms' => [
                'next_pms_date' => optional($vehicle->next_pms_date)->toDateString(),
                'days_remaining' => $pmsDays,
                'needs_attention' => $vehicle->needsPmsAlert(),
            ],
            'registration' => [
                'expiry_date' => optional($vehicle->latestRegistration?->expiry_date)->toDateString(),
                'days_remaining' => $regDays,
                'needs_attention' => $vehicle->needsRegistrationAlert(),
            ],
            'last_maintenance' => $vehicle->latestMaintenanceRecord ? [
                'service_date' => optional($vehicle->latestMaintenanceRecord->service_date)->toDateString(),
                'description' => $vehicle->latestMaintenanceRecord->description ?? null,
            ] : null,
            'last_fuel_log' => $vehicle->latestFuelLog ? [
                'refuel_date' => optional($vehicle->latestFuelLog->refuel_date)->toDateString(),
                'liters' => (float) $vehicle->latestFuelLog->liters,
                'km_per_liter' => $vehicle->latestFuelLog->kmPerLiter(),
            ] : null,
        ];
    }

    /**
     * What the in-app QR scanner hits once it's decoded a sticker — the
     * mobile equivalent of \App\Http\Controllers\ScanController::show() on
     * the desktop site. Open to DRIVER and VIEWER alike (same as every
     * other endpoint behind AuthenticateApiToken): this is a read-only
     * lookup, not a logging action, so there's no role branch to make here.
     */
    public function scan(Request $request, string $code): JsonResponse
    {
        $vehicle = Vehicle::with(['driver', 'type', 'encoder', 'registrations.uploader'])
            ->where('qr_code', $code)
            ->first();

        return $this->scanResponse($vehicle, $code, 'code');
    }

    /**
     * Manual-entry fallback for when the camera can't scan — looks up by
     * PLATE NUMBER, the only identifier actually printed and legible on the
     * physical sticker, mirroring ScanController::showByPlate() exactly.
     */
    public function scanByPlate(Request $request, string $plate): JsonResponse
    {
        $plate = strtoupper(trim($plate));

        $vehicle = Vehicle::with(['driver', 'type', 'encoder', 'registrations.uploader'])
            ->where('plate_number', $plate)
            ->first();

        return $this->scanResponse($vehicle, $plate, 'plate');
    }

    protected function scanResponse(?Vehicle $vehicle, string $identifier, string $identifierType): JsonResponse
    {
        if (! $vehicle) {
            return response()->json([
                'success' => false,
                'message' => $identifierType === 'plate'
                    ? "No vehicle is registered with the plate number {$identifier}."
                    : "This QR code isn't linked to any vehicle.",
                'identifier' => $identifier,
                'identifier_type' => $identifierType,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'vehicle' => $this->profilePayload($vehicle),
        ]);
    }

    /**
     * The full vehicle "ID card" profile shown on a successful scan —
     * mirrors every field resources/views/vehicles/scan-result.blade.php
     * renders, so the mobile scan result reads as the same record as the
     * desktop one. Registration documents are summarized (year/expiry/who
     * uploaded it) without OR/CR file links: those are served from an
     * authenticated web session on the desktop, not something this
     * bearer-token API can hand the app a usable URL for.
     */
    protected function profilePayload(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'plate_number' => strtoupper($vehicle->plate_number),
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'year_model' => $vehicle->year_model,
            'color' => $vehicle->color,
            'type' => optional($vehicle->type)->name,
            'status' => $vehicle->status,
            'engine_number' => $vehicle->engine_number,
            'chassis_number' => $vehicle->chassis_number,
            'odometer_km' => $vehicle->odometer_km,
            'next_pms_date' => optional($vehicle->next_pms_date)->toDateString(),
            'driver' => $vehicle->driver ? [
                'rank' => $vehicle->driver->rank,
                'firstname' => $vehicle->driver->firstname,
                'lastname' => $vehicle->driver->lastname,
                'contact_number' => $vehicle->driver->contact_number,
            ] : null,
            'registrations' => $vehicle->registrations->map(fn ($reg) => [
                'registration_year' => $reg->registration_year,
                'expiry_date' => optional($reg->expiry_date)->toDateString(),
                'uploaded_by' => optional($reg->uploader)->fullname,
                'uploaded_at' => optional($reg->created_at)->toDateString(),
            ])->values(),
            'encoded_by' => optional($vehicle->encoder)->fullname,
            'encoded_at' => optional($vehicle->created_at)->toDateString(),
        ];
    }
}
