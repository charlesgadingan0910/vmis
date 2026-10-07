<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Station;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Models\VehicleAccident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Vehicle Accident Records — a log of collisions/incidents involving a fleet
 * vehicle, separate from Maintenance/Repairs (see the migration's docblock).
 * Same unit/station visibility scoping and role handling as
 * MaintenanceController/RepairController/TripLogController.
 */
class VehicleAccidentController extends Controller
{
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';
    protected const ROLE_DRIVER        = 'DRIVER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Identical pattern to MaintenanceController::scopeToVisibleVehicles —
     * applies the current user's unit/station visibility to a vehicle-owning
     * query (either the Vehicle query itself, or an accident query via
     * whereHas('vehicle')).
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

    protected function canAccessVehicle(Vehicle $vehicle, $user, string $role): bool
    {
        if (in_array($role, self::BROAD_VISIBILITY_ROLES, true)) {
            return true;
        }
        if ($role === self::ROLE_UNIT_ADMIN) {
            return $vehicle->unit_id == $user->unit_id;
        }
        if ($role === self::ROLE_STATION_ADMIN) {
            return $vehicle->station_id == $user->station_id;
        }
        return false;
    }

    protected function authorizeVehicleWrite(Vehicle $vehicle): void
    {
        $user = auth()->user();
        $role = $this->role($user);

        if ($role === self::ROLE_VIEWER) {
            abort(403, 'Viewer accounts have read-only access.');
        }
        if (! $this->canAccessVehicle($vehicle, $user, $role)) {
            abort(403, 'Unauthorized Action');
        }
    }

    protected function severityBadge(string $severity): string
    {
        $label = VehicleAccident::SEVERITIES[$severity] ?? $severity;

        $class = match ($severity) {
            VehicleAccident::SEVERITY_MAJOR    => 'badge-severity-major',
            VehicleAccident::SEVERITY_MODERATE => 'badge-severity-moderate',
            default                             => 'badge-severity-minor',
        };

        return '<span class="severity-badge ' . $class . '">' . e($label) . '</span>';
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->role($user);

        // Same reasoning as MaintenanceController/RepairController — accident
        // reporting is an admin/motorpool responsibility in this app, not a
        // driver-facing one (drivers already have their own Trip Logs view).
        if ($role === self::ROLE_DRIVER) {
            abort(403, 'Driver accounts do not have access to Accident Records.');
        }

        $hasBroadVisibility = in_array($role, self::BROAD_VISIBILITY_ROLES, true);
        $isViewer = ($role === self::ROLE_VIEWER);

        if ($request->ajax()) {
            $query = VehicleAccident::with(['vehicle', 'driver', 'loggedBy'])
                ->orderByDesc('accident_date')
                ->orderByDesc('id');
            $this->scopeToVisibleVehicles($query, $user, $role, viaRelation: true);

            if ($vehicleId = $request->get('vehicle_id')) {
                $query->where('vehicle_id', $vehicleId);
            }
            if ($unitId = $request->get('unit_id')) {
                $query->whereHas('vehicle', fn ($q) => $q->where('unit_id', $unitId));
            }
            if ($stationId = $request->get('station_id')) {
                $query->whereHas('vehicle', fn ($q) => $q->where('station_id', $stationId));
            }
            if ($severity = $request->get('severity')) {
                $query->where('severity', $severity);
            }
            if ($dateFrom = $request->get('date_from')) {
                $query->whereDate('accident_date', '>=', $dateFrom);
            }
            if ($dateTo = $request->get('date_to')) {
                $query->whereDate('accident_date', '<=', $dateTo);
            }

            if ($search = trim((string) $request->input('search.value'))) {
                $query->where(function ($q) use ($search) {
                    $q->where('location', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('police_report_no', 'like', "%{$search}%")
                      ->orWhereHas('vehicle', function ($vq) use ($search) {
                          $vq->where('plate_number', 'like', "%{$search}%")
                             ->orWhere('make', 'like', "%{$search}%")
                             ->orWhere('model', 'like', "%{$search}%");
                      });
                });
            }

            $totalScoped = VehicleAccident::query();
            $this->scopeToVisibleVehicles($totalScoped, $user, $role, viaRelation: true);
            $totalRecords = $totalScoped->count();
            $filteredRecords = (clone $query)->count();

            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $accidents = $query->skip($start)->take($length)->get();

            $data = [];
            foreach ($accidents as $accident) {
                $vehicle = $accident->vehicle;

                $vehicleHtml = $vehicle
                    ? '<span class="plate-badge">' . e(strtoupper($vehicle->plate_number)) . '</span>' .
                      '<div class="vehicle-sub-info mt-1">' . e(trim($vehicle->make . ' ' . $vehicle->model)) . '</div>'
                    : '<span class="text-muted">Vehicle removed</span>';

                $driverName = $accident->driver ? trim($accident->driver->firstname . ' ' . $accident->driver->lastname) : '—';
                $driverHtml = '<div class="vehicle-sub-info">' . e($driverName) . '</div>';

                $dateHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . $accident->accident_date->format('M d, Y') . '</div>';
                if ($accident->accident_time) {
                    $dateHtml .= '<div class="vehicle-sub-info">' . date('h:i A', strtotime($accident->accident_time)) . '</div>';
                }

                $locationHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . e($accident->location) . '</div>' .
                    '<div class="vehicle-sub-info">' . e(Str::limit($accident->description, 60)) . '</div>';

                $costHtml = $accident->estimated_cost !== null ? '&#8369;' . number_format((float) $accident->estimated_cost, 2) : '<span class="text-muted">—</span>';

                $loggedHtml = '<div class="vehicle-sub-info">' . e(optional($accident->loggedBy)->fullname ?? 'Unknown user') . '</div>' .
                              '<div class="vehicle-sub-info">' . $accident->created_at->format('M d, Y') . '</div>';

                $photoBtn = '';
                if ($accident->photo_path) {
                    $photoUrl = route('accidents.document', ['path' => $accident->photo_path]);
                    $photoBtn = '<a href="' . e($photoUrl) . '" target="_blank" class="btn btn-sm btn-light border text-info" title="View Photo"><i class="fas fa-image"></i></a>';
                }

                if ($isViewer) {
                    $actionsHtml = '<div class="btn-group" role="group">' . $photoBtn . '</div>';
                } else {
                    $actionsHtml = '
                        <div class="btn-group" role="group">
                            ' . $photoBtn . '
                            <button type="button" class="btn btn-sm btn-light border text-primary btn-edit-accident" data-id="' . $accident->id . '" title="Edit"><i class="fas fa-edit"></i></button>
                            <button type="button" class="btn btn-sm btn-light border text-danger btn-delete-accident" data-id="' . $accident->id . '" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>';
                }

                $data[] = [
                    'vehicle_html'  => $vehicleHtml,
                    'date_html'     => $dateHtml,
                    'location_html' => $locationHtml,
                    'severity_html' => $this->severityBadge($accident->severity),
                    'driver_html'   => $driverHtml,
                    'cost_html'     => $costHtml,
                    'logged_html'   => $loggedHtml,
                    'actions_html'  => $actionsHtml,
                ];
            }

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $data,
            ]);
        }

        // ---------------- Initial (non-AJAX) page load ----------------
        $vehicleScope = Vehicle::query();
        $this->scopeToVisibleVehicles($vehicleScope, $user, $role);
        $vehicles = (clone $vehicleScope)->orderBy('plate_number')->get(['id', 'plate_number', 'make', 'model']);

        $accidentScope = VehicleAccident::query();
        $this->scopeToVisibleVehicles($accidentScope, $user, $role, viaRelation: true);

        $stats = [
            'total_accidents' => (clone $accidentScope)->count(),
            'this_month'      => (clone $accidentScope)->whereBetween('accident_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'major'           => (clone $accidentScope)->where('severity', VehicleAccident::SEVERITY_MAJOR)->count(),
            'total_est_cost'  => (clone $accidentScope)->sum('estimated_cost'),
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

        // Drivers offered in the "who was driving" picker — scoped the same
        // way the vehicle picker is, via each driver's currently assigned
        // vehicle (a driver with no vehicle assigned at all has no reason to
        // appear here).
        $drivers = Driver::whereIn('id', (clone $vehicleScope)->whereNotNull('assigned_driver_id')->pluck('assigned_driver_id'))
            ->orderBy('lastname')
            ->get(['id', 'firstname', 'lastname']);

        return view('accidents.index', compact(
            'stats', 'vehicles', 'units', 'stations', 'drivers', 'hasBroadVisibility', 'isViewer'
        ));
    }

    protected function validationRules(): array
    {
        return [
            'vehicle_id'        => ['required', 'exists:vehicles,id'],
            'driver_id'         => ['nullable', 'exists:drivers,id'],
            'accident_date'     => ['required', 'date'],
            'accident_time'     => ['nullable', 'date_format:H:i'],
            'location'          => ['required', 'string', 'max:255'],
            'description'       => ['required', 'string', 'max:2000'],
            'severity'          => ['required', Rule::in(array_keys(VehicleAccident::SEVERITIES))],
            'estimated_cost'    => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'police_report_no'  => ['nullable', 'string', 'max:100'],
            'photo'             => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->validationRules());

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $this->authorizeVehicleWrite($vehicle);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('accident_photos', 'public');
        }
        unset($validated['photo']);

        $validated['logged_by'] = auth()->id();

        $accident = VehicleAccident::create($validated);

        ActivityLog::record(
            'created',
            'Vehicle Accident',
            'Logged a ' . strtolower(VehicleAccident::SEVERITIES[$accident->severity] ?? $accident->severity) . ' accident for [' . strtoupper($vehicle->plate_number) . '] at ' . $accident->location . '.',
            $accident,
            ['after' => \Illuminate\Support\Arr::except($validated, ['photo'])]
        );

        return response()->json([
            'success' => true,
            'message' => 'Accident record logged for [' . strtoupper($vehicle->plate_number) . '].',
        ]);
    }

    public function editData(VehicleAccident $accident): JsonResponse
    {
        $this->authorizeVehicleWrite($accident->vehicle);

        return response()->json([
            'id'                => $accident->id,
            'vehicle_id'        => $accident->vehicle_id,
            'driver_id'         => $accident->driver_id,
            'accident_date'     => optional($accident->accident_date)->format('Y-m-d'),
            'accident_time'     => $accident->accident_time ? date('H:i', strtotime($accident->accident_time)) : null,
            'location'          => $accident->location,
            'description'       => $accident->description,
            'severity'          => $accident->severity,
            'estimated_cost'    => $accident->estimated_cost,
            'police_report_no'  => $accident->police_report_no,
            'has_photo'         => (bool) $accident->photo_path,
            'photo_url'         => $accident->photo_path ? route('accidents.document', ['path' => $accident->photo_path]) : null,
        ]);
    }

    public function update(Request $request, VehicleAccident $accident): JsonResponse
    {
        $this->authorizeVehicleWrite($accident->vehicle);

        $rules = $this->validationRules();
        $rules['remove_photo'] = ['nullable', 'boolean'];

        $validated = $request->validate($rules);

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $this->authorizeVehicleWrite($vehicle);

        if ($request->hasFile('photo')) {
            if ($accident->photo_path) {
                Storage::disk('public')->delete($accident->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('accident_photos', 'public');
        } elseif ($request->boolean('remove_photo') && $accident->photo_path) {
            Storage::disk('public')->delete($accident->photo_path);
            $validated['photo_path'] = null;
        }
        unset($validated['photo'], $validated['remove_photo']);

        $accident->update($validated);

        ActivityLog::record(
            'updated',
            'Vehicle Accident',
            'Updated the accident record for [' . strtoupper($vehicle->plate_number) . '] at ' . $accident->location . '.',
            $accident,
            ['after' => $validated]
        );

        return response()->json([
            'success' => true,
            'message' => 'Accident record updated for [' . strtoupper($vehicle->plate_number) . '].',
        ]);
    }

    public function destroy(VehicleAccident $accident): JsonResponse
    {
        $this->authorizeVehicleWrite($accident->vehicle);

        $plate = strtoupper($accident->vehicle->plate_number ?? 'UNKNOWN');
        $location = $accident->location;

        if ($accident->photo_path) {
            Storage::disk('public')->delete($accident->photo_path);
        }

        $accident->delete();

        ActivityLog::record(
            'deleted',
            'Vehicle Accident',
            'Deleted the accident record for [' . $plate . '] at ' . $location . '.',
        );

        return response()->json([
            'success' => true,
            'message' => 'Accident record deleted.',
        ]);
    }

    /**
     * Serves an uploaded accident photo directly (bypasses the public/storage
     * symlink, which is unreliable on Windows/WAMP) and keeps document access
     * behind login — same convention as MaintenanceController::viewDocument.
     */
    public function viewDocument(string $path)
    {
        if (! str_starts_with($path, 'accident_photos/') || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }
}
