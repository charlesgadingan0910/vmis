<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fuel Monitoring for the VMIS mobile app. Mirrors
 * \App\Http\Controllers\FuelLogController::store()'s DRIVER branch exactly,
 * including the optional receipt upload, so entries logged from the app
 * land in the exact same table the desktop Fuel Monitoring module reads.
 *
 * A VIEWER never logs a refuel (read-only, matching the desktop's
 * ROLE_VIEWER exclusion) but sees every vehicle's fuel logs fleet-wide,
 * optionally narrowed to one vehicle.
 */
class FuelLogController extends Controller
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

        $logs = FuelLog::with('vehicle')
            ->where('driver_id', $driver->id)
            ->orderByDesc('refuel_date')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'total' => $logs->total(),
            'fuel_logs' => collect($logs->items())->map(fn (FuelLog $log) => $this->logPayload($log)),
        ]);
    }

    protected function viewerIndex(Request $request): JsonResponse
    {
        $query = FuelLog::with('vehicle')->orderByDesc('refuel_date')->orderByDesc('id');

        if ($vehicleId = $request->integer('vehicle_id')) {
            $query->where('vehicle_id', $vehicleId);
        }

        $logs = $query->paginate(20);

        return response()->json([
            'success' => true,
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'total' => $logs->total(),
            'fuel_logs' => collect($logs->items())->map(fn (FuelLog $log) => $this->logPayload($log)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->isViewer($request)) {
            return response()->json(['success' => false, 'message' => 'Your account type is not permitted to log refuels.'], 403);
        }

        $driver = $this->resolveDriver($request);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Your account is not linked to a driver profile yet. Contact your administrator.'], 403);
        }

        $validated = $request->validate([
            'refuel_date' => ['required', 'date'],
            'liters' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'total_cost' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'odometer_reading' => ['required', 'integer', 'min:0'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'vehicle_id' => ['nullable', 'integer'],
        ]);

        $vehicle = Vehicle::where('assigned_driver_id', $driver->id)
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('id', $request->integer('vehicle_id')))
            ->first();

        if (! $vehicle) {
            return response()->json(['success' => false, 'message' => 'No vehicle is currently assigned to you. Contact your administrator.'], 403);
        }

        unset($validated['vehicle_id']);
        $validated['vehicle_id'] = $vehicle->id;
        $validated['driver_id'] = $driver->id;
        $validated['logged_by'] = $request->user()->id;

        if ($request->hasFile('receipt')) {
            $validated['receipt_path'] = $request->file('receipt')->store('fuel_logs/receipts', 'public');
        }
        unset($validated['receipt']);

        $log = FuelLog::create($validated);

        ActivityLog::record(
            'created',
            'Fuel Log',
            'Logged a refuel for [' . strtoupper($vehicle->plate_number) . '] (' . number_format((float) $validated['liters'], 2) . ' L, ₱' . number_format((float) $validated['total_cost'], 2) . ') via mobile app.',
            $log,
            ['after' => $validated]
        );

        return response()->json([
            'success' => true,
            'message' => 'Refuel logged for [' . strtoupper($vehicle->plate_number) . '].',
            'fuel_log' => $this->logPayload($log->fresh('vehicle')),
        ], 201);
    }

    protected function resolveDriver(Request $request): ?Driver
    {
        $user = $request->user();

        return $user->driver_id ? Driver::find($user->driver_id) : null;
    }

    protected function logPayload(FuelLog $log): array
    {
        return [
            'id' => $log->id,
            'vehicle_plate_number' => $log->vehicle ? strtoupper($log->vehicle->plate_number) : null,
            'refuel_date' => optional($log->refuel_date)->toDateString(),
            'liters' => (float) $log->liters,
            'total_cost' => (float) $log->total_cost,
            'price_per_liter' => $log->pricePerLiter(),
            'odometer_reading' => $log->odometer_reading,
            'distance_since_last_refuel' => $log->distanceSinceLastRefuel(),
            'km_per_liter' => $log->kmPerLiter(),
            'has_receipt' => (bool) $log->receipt_path,
            'logged_at' => optional($log->created_at)->toIso8601String(),
        ];
    }
}
