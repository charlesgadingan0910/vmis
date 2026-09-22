<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use App\Models\Unit;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    // Mirrors VehicleController's role handling exactly — maintenance records inherit
    // their visibility from the vehicle they belong to, so the same unit/station
    // scoping has to apply here too, not just on the Vehicle Inventory page.
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    // A vehicle's PMS is flagged "due soon" inside this many days — kept identical to
    // the threshold VehicleController already uses for the Vehicle Inventory PMS badge,
    // so a vehicle reads the same way (soon vs. overdue) on both pages.
    protected const DUE_SOON_DAYS = 14;

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Applies the current user's unit/station visibility to a vehicle-owning query
     * (either the Vehicle query itself, or a maintenance query via whereHas('vehicle')).
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

    /**
     * Re-derives a vehicle's next_pms_date / odometer_km from whatever its most recent
     * maintenance record now says, after any create/update/delete. This is what keeps
     * the Vehicle Inventory page's PMS badge (and the vehicle's own odometer reading)
     * truthful without duplicating that logic at every call site.
     */
    protected function syncVehicleFromLatestRecord(Vehicle $vehicle): void
    {
        $latest = MaintenanceRecord::where('vehicle_id', $vehicle->id)
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->first();

        $vehicle->next_pms_date = $latest?->next_due_date;

        // Odometer only ever moves forward from a logged reading — never blanked out
        // just because the latest record happened not to include one.
        if ($latest?->odometer_km) {
            $vehicle->odometer_km = $latest->odometer_km;
        }

        $vehicle->save();
    }

    /**
     * Overdue/due-soon bucket + a human day count for a given next_pms_date, shared by
     * both the monitoring panel payload and the per-row "Next Due" pill.
     */
    protected function dueBucket(?Carbon $nextPmsDate): array
    {
        if (! $nextPmsDate) {
            return ['bucket' => 'none', 'days' => null];
        }

        $days = now()->startOfDay()->diffInDays($nextPmsDate->copy()->startOfDay(), false);

        if ($days < 0) {
            return ['bucket' => 'overdue', 'days' => $days];
        }
        if ($days <= self::DUE_SOON_DAYS) {
            return ['bucket' => 'soon', 'days' => $days];
        }
        return ['bucket' => 'normal', 'days' => $days];
    }

    protected function nextDueHtml(?Carbon $nextPmsDate): string
    {
        if (! $nextPmsDate) {
            return '<span class="due-pill due-none"><i class="fas fa-minus"></i> Not scheduled</span>';
        }

        ['bucket' => $bucket] = $this->dueBucket($nextPmsDate);
        $formatted = $nextPmsDate->format('M d, Y');

        return match ($bucket) {
            'overdue' => '<span class="due-pill due-overdue"><i class="fas fa-exclamation-triangle"></i> ' . $formatted . '</span>',
            'soon'    => '<span class="due-pill due-soon"><i class="fas fa-clock"></i> ' . $formatted . '</span>',
            default   => '<span class="due-pill due-normal"><i class="fas fa-check"></i> ' . $formatted . '</span>',
        };
    }

    /**
     * Display maintenance history with dynamic filters, live monitoring counts, and a
     * server-side-paginated DataTable — the same shape VehicleController uses.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->role($user);
        $hasBroadVisibility = in_array($role, self::BROAD_VISIBILITY_ROLES, true);
        $isViewer = ($role === self::ROLE_VIEWER);

        if ($request->ajax()) {
            $query = MaintenanceRecord::with(['vehicle.type', 'recorder'])
                ->orderByDesc('service_date')
                ->orderByDesc('id');

            $this->scopeToVisibleVehicles($query, $user, $role, viaRelation: true);

            if ($unitId = $request->get('unit_id')) {
                $query->whereHas('vehicle', fn ($q) => $q->where('unit_id', $unitId));
            }
            if ($stationId = $request->get('station_id')) {
                $query->whereHas('vehicle', fn ($q) => $q->where('station_id', $stationId));
            }
            if ($vehicleId = $request->get('vehicle_id')) {
                $query->where('vehicle_id', $vehicleId);
            }
            if ($type = $request->get('maintenance_type')) {
                $query->where('maintenance_type', $type);
            }
            if ($dateFrom = $request->get('date_from')) {
                $query->whereDate('service_date', '>=', $dateFrom);
            }
            if ($dateTo = $request->get('date_to')) {
                $query->whereDate('service_date', '<=', $dateTo);
            }

            if ($search = trim((string) $request->input('search.value'))) {
                $query->where(function ($q) use ($search) {
                    $q->where('performed_by', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('vehicle', function ($vq) use ($search) {
                          $vq->where('plate_number', 'like', "%{$search}%")
                             ->orWhere('make', 'like', "%{$search}%")
                             ->orWhere('model', 'like', "%{$search}%");
                      });
                });
            }

            $totalScoped = MaintenanceRecord::query();
            $this->scopeToVisibleVehicles($totalScoped, $user, $role, viaRelation: true);
            $totalRecords = $totalScoped->count();
            $filteredRecords = (clone $query)->count();

            // ---------------- Monitoring snapshot ----------------
            // Recomputed against the vehicle scope + unit/station filter (not the record-level
            // search/type/date filters) so the panel always reflects "vehicles in view", not
            // whatever the table's text search happens to match.
            $vehicleScope = Vehicle::query()->whereNotNull('next_pms_date');
            $this->scopeToVisibleVehicles($vehicleScope, $user, $role);
            if ($unitId) {
                $vehicleScope->where('unit_id', $unitId);
            }
            if ($stationId) {
                $vehicleScope->where('station_id', $stationId);
            }

            $monitorVehicles = $vehicleScope->orderBy('next_pms_date')->get(['id', 'plate_number', 'make', 'model', 'next_pms_date']);

            $dueSoon = [];
            $overdue = [];
            foreach ($monitorVehicles as $v) {
                ['bucket' => $bucket, 'days' => $days] = $this->dueBucket($v->next_pms_date);
                $item = [
                    'id'            => $v->id,
                    'plate_number'  => strtoupper($v->plate_number),
                    'make_model'    => trim($v->make . ' ' . $v->model),
                    'next_pms_date' => $v->next_pms_date->format('M d, Y'),
                    'days'          => (int) $days,
                ];
                if ($bucket === 'overdue') {
                    $overdue[] = $item;
                } elseif ($bucket === 'soon') {
                    $dueSoon[] = $item;
                }
            }

            // ---------------- Stat cards ----------------
            $recordsThisMonthScope = MaintenanceRecord::query()->whereBetween('service_date', [
                now()->startOfMonth(), now()->endOfMonth(),
            ]);
            $this->scopeToVisibleVehicles($recordsThisMonthScope, $user, $role, viaRelation: true);
            if ($unitId) {
                $recordsThisMonthScope->whereHas('vehicle', fn ($q) => $q->where('unit_id', $unitId));
            }
            if ($stationId) {
                $recordsThisMonthScope->whereHas('vehicle', fn ($q) => $q->where('station_id', $stationId));
            }

            $stats = [
                'total_records'  => $filteredRecords,
                'due_soon'       => count($dueSoon),
                'overdue'        => count($overdue),
                'serviced_month' => $recordsThisMonthScope->count(),
            ];

            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $records = $query->skip($start)->take($length)->get();

            $data = [];
            foreach ($records as $record) {
                $vehicle = $record->vehicle;

                $vehicleHtml = $vehicle
                    ? '<span class="plate-badge">' . e(strtoupper($vehicle->plate_number)) . '</span>' .
                      '<div class="vehicle-sub-info mt-1">' . e(trim($vehicle->make . ' ' . $vehicle->model)) . '</div>'
                    : '<span class="text-muted">Vehicle removed</span>';

                $typeColor = MaintenanceRecord::TYPE_COLORS[$record->maintenance_type] ?? 'light';
                $typeLabel = MaintenanceRecord::TYPES[$record->maintenance_type] ?? $record->maintenance_type;
                $typeHtml = '<span class="badge badge-' . $typeColor . ' px-2 py-1" style="font-size:11.5px;font-weight:700;">' . e($typeLabel) . '</span>';
                if ($record->description) {
                    $typeHtml .= '<div class="vehicle-sub-info mt-1">' . e(Str::limit($record->description, 60)) . '</div>';
                }

                $serviceHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . $record->service_date->format('M d, Y') . '</div>';
                if ($record->odometer_km) {
                    $serviceHtml .= '<div class="vehicle-sub-info">' . number_format($record->odometer_km) . ' km</div>';
                }

                $costHtml = $record->cost !== null
                    ? '<span class="font-weight-bold">&#8369;' . number_format((float) $record->cost, 2) . '</span>'
                    : '<span class="text-muted">—</span>';

                $nextDueHtml = $this->nextDueHtml($record->next_due_date);
                if ($record->next_due_odometer_km) {
                    $nextDueHtml .= '<div class="vehicle-sub-info mt-1">at ' . number_format($record->next_due_odometer_km) . ' km</div>';
                }

                $loggedHtml = '<div class="vehicle-sub-info">' . e(optional($record->recorder)->fullname ?? 'System') . '</div>' .
                              '<div class="vehicle-sub-info">' . $record->created_at->format('M d, Y') . '</div>';

                $attachmentBtn = $record->attachment_path
                    ? '<a href="' . route('maintenance.document', ['path' => $record->attachment_path]) . '" target="_blank" class="btn btn-sm btn-light border text-secondary" title="View Attachment"><i class="fas fa-paperclip"></i></a>'
                    : '';

                if ($isViewer) {
                    $actionsHtml = '<div class="btn-group" role="group">' . $attachmentBtn . '</div>';
                } else {
                    $actionsHtml = '
                        <div class="btn-group" role="group">
                            ' . $attachmentBtn . '
                            <button type="button" class="btn btn-sm btn-light border text-primary btn-edit-maintenance" data-id="' . $record->id . '" title="Edit"><i class="fas fa-edit"></i></button>
                            <button type="button" class="btn btn-sm btn-light border text-danger btn-delete-maintenance" data-id="' . $record->id . '" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>';
                }

                $data[] = [
                    'vehicle_html'    => $vehicleHtml,
                    'type_html'       => $typeHtml,
                    'service_html'    => $serviceHtml,
                    'cost_html'       => $costHtml,
                    'next_due_html'   => $nextDueHtml,
                    'logged_html'     => $loggedHtml,
                    'actions_html'    => $actionsHtml,
                ];
            }

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $data,
                'stats'           => $stats,
                'monitoring'      => ['due_soon' => $dueSoon, 'overdue' => $overdue],
            ]);
        }

        // ---------------- Initial (non-AJAX) page load ----------------
        $vehicleScope = Vehicle::query();
        $this->scopeToVisibleVehicles($vehicleScope, $user, $role);
        $vehicles = (clone $vehicleScope)->orderBy('plate_number')->get(['id', 'plate_number', 'make', 'model', 'odometer_km']);

        $monitorScope = (clone $vehicleScope)->whereNotNull('next_pms_date')->get(['next_pms_date']);
        $dueSoonCount = 0;
        $overdueCount = 0;
        foreach ($monitorScope as $v) {
            $bucket = $this->dueBucket($v->next_pms_date)['bucket'];
            if ($bucket === 'overdue') { $overdueCount++; }
            elseif ($bucket === 'soon') { $dueSoonCount++; }
        }

        $recordScope = MaintenanceRecord::query();
        $this->scopeToVisibleVehicles($recordScope, $user, $role, viaRelation: true);

        $stats = [
            'total_records'  => (clone $recordScope)->count(),
            'due_soon'       => $dueSoonCount,
            'overdue'        => $overdueCount,
            'serviced_month' => (clone $recordScope)->whereBetween('service_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
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

        return view('maintenance.index', compact(
            'stats', 'vehicles', 'units', 'stations', 'hasBroadVisibility', 'isViewer'
        ));
    }

    /**
     * Log a new maintenance activity for a vehicle. Optionally sets what the vehicle's
     * next PMS/odometer should read from here on (synced via syncVehicleFromLatestRecord).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id'            => ['required', 'exists:vehicles,id'],
            'maintenance_type'      => ['required', Rule::in(array_keys(MaintenanceRecord::TYPES))],
            'description'           => ['nullable', 'string', 'max:1000'],
            'service_date'          => ['required', 'date'],
            'odometer_km'           => ['nullable', 'integer', 'min:0'],
            'cost'                  => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'performed_by'          => ['nullable', 'string', 'max:150'],
            'next_due_date'         => ['nullable', 'date', 'after_or_equal:service_date'],
            'next_due_odometer_km'  => ['nullable', 'integer', 'min:0'],
            'attachment'            => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $this->authorizeVehicleWrite($vehicle);

        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store('maintenance_docs', 'public');
        }
        $validated['recorded_by'] = auth()->id();

        MaintenanceRecord::create($validated);

        $this->syncVehicleFromLatestRecord($vehicle);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance record logged for [' . strtoupper($vehicle->plate_number) . '].',
        ]);
    }

    /**
     * Raw field values used to populate the Edit modal via AJAX.
     */
    public function editData(MaintenanceRecord $maintenance): JsonResponse
    {
        $this->authorizeVehicleWrite($maintenance->vehicle);

        return response()->json([
            'id'                    => $maintenance->id,
            'vehicle_id'            => $maintenance->vehicle_id,
            'vehicle_label'         => strtoupper($maintenance->vehicle->plate_number) . ' — ' . trim($maintenance->vehicle->make . ' ' . $maintenance->vehicle->model),
            'maintenance_type'      => $maintenance->maintenance_type,
            'description'           => $maintenance->description,
            'service_date'          => optional($maintenance->service_date)->format('Y-m-d'),
            'odometer_km'           => $maintenance->odometer_km,
            'cost'                  => $maintenance->cost,
            'performed_by'          => $maintenance->performed_by,
            'next_due_date'         => optional($maintenance->next_due_date)->format('Y-m-d'),
            'next_due_odometer_km'  => $maintenance->next_due_odometer_km,
            'has_attachment'        => (bool) $maintenance->attachment_path,
            'attachment_url'        => $maintenance->attachment_path ? route('maintenance.document', ['path' => $maintenance->attachment_path]) : null,
        ]);
    }

    /**
     * Update an existing record. Sent as POST + _method=PUT (not a plain PUT) because a
     * replacement attachment needs real multipart file upload support — PHP never
     * populates $_FILES for a native PUT request, spoofed POST is the standard workaround.
     */
    public function update(Request $request, MaintenanceRecord $maintenance): JsonResponse
    {
        $this->authorizeVehicleWrite($maintenance->vehicle);

        $validated = $request->validate([
            'vehicle_id'            => ['required', 'exists:vehicles,id'],
            'maintenance_type'      => ['required', Rule::in(array_keys(MaintenanceRecord::TYPES))],
            'description'           => ['nullable', 'string', 'max:1000'],
            'service_date'          => ['required', 'date'],
            'odometer_km'           => ['nullable', 'integer', 'min:0'],
            'cost'                  => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'performed_by'          => ['nullable', 'string', 'max:150'],
            'next_due_date'         => ['nullable', 'date', 'after_or_equal:service_date'],
            'next_due_odometer_km'  => ['nullable', 'integer', 'min:0'],
            'attachment'            => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_attachment'     => ['nullable', 'boolean'],
        ]);

        $newVehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $this->authorizeVehicleWrite($newVehicle);

        $oldVehicle = $maintenance->vehicle;

        if ($request->hasFile('attachment')) {
            if ($maintenance->attachment_path) {
                Storage::disk('public')->delete($maintenance->attachment_path);
            }
            $validated['attachment_path'] = $request->file('attachment')->store('maintenance_docs', 'public');
        } elseif ($request->boolean('remove_attachment') && $maintenance->attachment_path) {
            Storage::disk('public')->delete($maintenance->attachment_path);
            $validated['attachment_path'] = null;
        }
        unset($validated['remove_attachment']);

        $maintenance->update($validated);

        // Re-sync whichever vehicle(s) could be affected — both the old one (if this
        // record was moved off it) and the new/current one.
        $this->syncVehicleFromLatestRecord($newVehicle);
        if ($oldVehicle && $oldVehicle->id !== $newVehicle->id) {
            $this->syncVehicleFromLatestRecord($oldVehicle);
        }

        return response()->json([
            'success' => true,
            'message' => 'Maintenance record updated successfully.',
        ]);
    }

    public function destroy(MaintenanceRecord $maintenance): JsonResponse
    {
        $this->authorizeVehicleWrite($maintenance->vehicle);

        $vehicle = $maintenance->vehicle;

        if ($maintenance->attachment_path) {
            Storage::disk('public')->delete($maintenance->attachment_path);
        }
        $maintenance->delete();

        if ($vehicle) {
            $this->syncVehicleFromLatestRecord($vehicle);
        }

        return response()->json([
            'success' => true,
            'message' => 'Maintenance record deleted.',
        ]);
    }

    /**
     * Serves an uploaded receipt/invoice directly from the private storage disk —
     * same rationale as VehicleController::viewDocument (bypasses the public/storage
     * symlink, which is unreliable on Windows/WAMP, and keeps it behind auth).
     */
    public function viewDocument(string $path)
    {
        if (! str_starts_with($path, 'maintenance_docs/') || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }
}
