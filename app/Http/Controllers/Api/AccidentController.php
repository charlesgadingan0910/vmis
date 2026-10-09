<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAccident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Accident/incident reporting for the Driver mobile app.
 *
 * Note: the desktop \App\Http\Controllers\VehicleAccidentController does not
 * currently let a DRIVER-role account file a report at all (its
 * canAccessVehicle() has no DRIVER case) — filing from the field is a new
 * capability the app adds, so this resolves the driver's own assigned
 * vehicle the same way TripLogController/FuelLogController do rather than
 * reusing that method. Validation rules mirror
 * VehicleAccidentController::validationRules() exactly.
 */
class AccidentController extends Controller
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

        $reports = VehicleAccident::with('vehicle')
            ->where('driver_id', $driver->id)
            ->orderByDesc('accident_date')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'current_page' => $reports->currentPage(),
            'last_page' => $reports->lastPage(),
            'total' => $reports->total(),
            'accidents' => collect($reports->items())->map(fn (VehicleAccident $a) => $this->reportPayload($a)),
        ]);
    }

    protected function viewerIndex(Request $request): JsonResponse
    {
        $query = VehicleAccident::with('vehicle')->orderByDesc('accident_date')->orderByDesc('id');

        if ($vehicleId = $request->integer('vehicle_id')) {
            $query->where('vehicle_id', $vehicleId);
        }

        $reports = $query->paginate(20);

        return response()->json([
            'success' => true,
            'current_page' => $reports->currentPage(),
            'last_page' => $reports->lastPage(),
            'total' => $reports->total(),
            'accidents' => collect($reports->items())->map(fn (VehicleAccident $a) => $this->reportPayload($a)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->isViewer($request)) {
            return response()->json(['success' => false, 'message' => 'Viewer accounts have read-only access.'], 403);
        }

        $driver = $this->resolveDriver($request);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Your account is not linked to a driver profile yet. Contact your administrator.'], 403);
        }

        $validated = $request->validate([
            'accident_date' => ['required', 'date'],
            'accident_time' => ['nullable', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'severity' => ['required', Rule::in(array_keys(VehicleAccident::SEVERITIES))],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'police_report_no' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
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

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('accident_photos', 'public');
        }
        unset($validated['photo']);

        $accident = VehicleAccident::create($validated);

        ActivityLog::record(
            'created',
            'Vehicle Accident',
            'Logged a ' . strtolower(VehicleAccident::SEVERITIES[$accident->severity] ?? $accident->severity) . ' accident for [' . strtoupper($vehicle->plate_number) . '] at ' . $accident->location . ' via mobile app.',
            $accident,
            ['after' => $validated]
        );

        return response()->json([
            'success' => true,
            'message' => 'Accident record logged for [' . strtoupper($vehicle->plate_number) . '].',
            'accident' => $this->reportPayload($accident->fresh('vehicle')),
        ], 201);
    }

    protected function resolveDriver(Request $request): ?Driver
    {
        $user = $request->user();

        return $user->driver_id ? Driver::find($user->driver_id) : null;
    }

    protected function reportPayload(VehicleAccident $accident): array
    {
        return [
            'id' => $accident->id,
            'vehicle_plate_number' => $accident->vehicle ? strtoupper($accident->vehicle->plate_number) : null,
            'accident_date' => optional($accident->accident_date)->toDateString(),
            'accident_time' => $accident->accident_time,
            'location' => $accident->location,
            'description' => $accident->description,
            'severity' => $accident->severity,
            'severity_label' => VehicleAccident::SEVERITIES[$accident->severity] ?? $accident->severity,
            'estimated_cost' => $accident->estimated_cost !== null ? (float) $accident->estimated_cost : null,
            'police_report_no' => $accident->police_report_no,
            'has_photo' => (bool) $accident->photo_path,
            'logged_at' => optional($accident->created_at)->toIso8601String(),
        ];
    }
}
