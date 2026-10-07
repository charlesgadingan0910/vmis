<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use App\Models\Unit;
use App\Models\Station;
use App\Services\DocumentIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * VMIS Additional Updates item 4: "Different Module for Repairs and
 * Maintenance." Repairs still live in the same maintenance_records table as
 * Maintenance & PMS (maintenance_type = REPAIR) — no migration needed for
 * the split — but this controller only ever touches REPAIR rows, and
 * MaintenanceController now explicitly excludes them (see its own
 * excludingRepairs() calls and the abort_if() guards on editData/update/
 * destroy). The role/visibility handling below mirrors MaintenanceController
 * exactly, same as that controller mirrors VehicleController's.
 */
class RepairController extends Controller
{
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Applies the current user's unit/station visibility to a vehicle-owning query
     * (either the Vehicle query itself, or a repairs query via whereHas('vehicle')).
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
     * COMPLETED maintenance-or-repair record now says — identical to
     * MaintenanceController's own version (see its docblock for why only Completed
     * rows count: a Requested/Inspected/Awaiting-Parts record's service_date is just
     * a request_date placeholder).
     */
    protected function syncVehicleFromLatestRecord(Vehicle $vehicle): void
    {
        $latest = MaintenanceRecord::where('vehicle_id', $vehicle->id)
            ->completedOnly()
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->first();

        $vehicle->next_pms_date = $latest?->next_due_date;

        if ($latest?->odometer_km) {
            $vehicle->odometer_km = $latest->odometer_km;
        }

        $vehicle->save();
    }

    /**
     * Stores, replaces, or removes one of the named form-attachment slots
     * (Technical Inspection Report, Vehicle Repair Requisition Slip) exactly
     * like the receipt/invoice attachment — parameterized so the same logic
     * covers both store() (existingPath is null) and update() (existingPath
     * is whatever's already on the record).
     */
    protected function applyFormAttachment(Request $request, array $validated, ?string $existingPath, string $fileField, string $removeField, string $pathColumn, string $storageDir): array
    {
        if ($request->hasFile($fileField)) {
            if ($existingPath) {
                Storage::disk('public')->delete($existingPath);
            }
            $validated[$pathColumn] = $request->file($fileField)->store($storageDir, 'public');
        } elseif ($request->boolean($removeField) && $existingPath) {
            Storage::disk('public')->delete($existingPath);
            $validated[$pathColumn] = null;
        }
        unset($validated[$removeField]);

        return $validated;
    }

    /**
     * Display repair history with dynamic filters and a server-side-paginated
     * DataTable — same shape as Maintenance & PMS, minus the PMS due-date
     * monitoring panel (that's a preventive-schedule concept, not a repairs one).
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->role($user);

        if ($role === 'DRIVER') {
            abort(403, 'Driver accounts do not have access to Repairs.');
        }

        $hasBroadVisibility = in_array($role, self::BROAD_VISIBILITY_ROLES, true);
        $isViewer = ($role === self::ROLE_VIEWER);

        if ($request->ajax()) {
            $query = MaintenanceRecord::with(['vehicle.type', 'recorder'])
                ->repairsOnly()
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
                      ->orWhere('control_number', 'like', "%{$search}%")
                      ->orWhereHas('vehicle', function ($vq) use ($search) {
                          $vq->where('plate_number', 'like', "%{$search}%")
                             ->orWhere('make', 'like', "%{$search}%")
                             ->orWhere('model', 'like', "%{$search}%");
                      });
                });
            }

            $totalScoped = MaintenanceRecord::query()->repairsOnly();
            $this->scopeToVisibleVehicles($totalScoped, $user, $role, viaRelation: true);
            $totalRecords = $totalScoped->count();
            $filteredRecords = (clone $query)->count();

            // ---------------- Stat cards ----------------
            // Cost/"this month" only ever meant completed jobs — a Requested/Inspected/
            // Awaiting-Parts repair has no real cost yet and its service_date is just a
            // request_date placeholder (see the stage-workflow migration), so these have
            // to filter to completedOnly() or an in-flight repair would skew them.
            $monthScope = MaintenanceRecord::query()->repairsOnly()->completedOnly()->whereBetween('service_date', [
                now()->startOfMonth(), now()->endOfMonth(),
            ]);
            $this->scopeToVisibleVehicles($monthScope, $user, $role, viaRelation: true);
            if ($unitId) {
                $monthScope->whereHas('vehicle', fn ($q) => $q->where('unit_id', $unitId));
            }
            if ($stationId) {
                $monthScope->whereHas('vehicle', fn ($q) => $q->where('station_id', $stationId));
            }

            $yearScope = MaintenanceRecord::query()->repairsOnly()->completedOnly()->whereBetween('service_date', [
                now()->startOfYear(), now()->endOfYear(),
            ]);
            $this->scopeToVisibleVehicles($yearScope, $user, $role, viaRelation: true);
            if ($unitId) {
                $yearScope->whereHas('vehicle', fn ($q) => $q->where('unit_id', $unitId));
            }
            if ($stationId) {
                $yearScope->whereHas('vehicle', fn ($q) => $q->where('station_id', $stationId));
            }

            $stats = [
                'total_records'    => $filteredRecords,
                'this_month'       => (clone $monthScope)->count(),
                'this_month_cost'  => (clone $monthScope)->sum('cost'),
                'this_year_cost'   => (clone $yearScope)->sum('cost'),
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

                // Process-flow stage (VMIS process-flow redesign: Requested -> Inspected
                // -> Awaiting Parts -> Completed). See MaintenanceRecord::STAGE_* consts.
                $stageColor = MaintenanceRecord::STAGE_COLORS[$record->stage] ?? 'secondary';
                $stageLabel = MaintenanceRecord::STAGES[$record->stage] ?? $record->stage;
                $stageHtml = '<span class="badge badge-' . $stageColor . ' px-2 py-1" style="font-size:11px;font-weight:700;">' . e($stageLabel) . '</span>';
                if ($record->control_number) {
                    $stageHtml .= '<div class="vehicle-sub-info mt-1">' . e($record->control_number) . '</div>';
                }

                $descHtml = $record->description
                    ? '<div class="vehicle-main-name" style="font-size:13.5px;">' . e(Str::limit($record->description, 70)) . '</div>'
                    : '<span class="text-muted">No description provided</span>';

                // Before Completed, service_date is just the request_date placeholder —
                // label it accordingly so the table doesn't look like the job is already done.
                $serviceDateLabel = $record->stage === MaintenanceRecord::STAGE_COMPLETED ? '' : ' <span class="text-muted" style="font-weight:400;">(requested)</span>';
                $serviceHtml = '<div class="vehicle-main-name" style="font-size:13.5px;">' . $record->service_date->format('M d, Y') . $serviceDateLabel . '</div>';
                if ($record->odometer_km) {
                    $serviceHtml .= '<div class="vehicle-sub-info">' . number_format($record->odometer_km) . ' km</div>';
                }

                $costHtml = $record->cost !== null
                    ? '<span class="font-weight-bold">&#8369;' . number_format((float) $record->cost, 2) . '</span>'
                    : '<span class="text-muted">—</span>';

                $shopHtml = $record->performed_by
                    ? '<div class="vehicle-sub-info">' . e($record->performed_by) . '</div>'
                    : '<span class="text-muted">—</span>';

                $loggedHtml = '<div class="vehicle-sub-info">' . e(optional($record->recorder)->fullname ?? 'System') . '</div>' .
                              '<div class="vehicle-sub-info">' . $record->created_at->format('M d, Y') . '</div>';

                $attachmentBtn = $record->attachment_path
                    ? '<a href="' . route('repairs.document', ['path' => $record->attachment_path]) . '" target="_blank" class="btn btn-sm btn-light border text-secondary" title="View Receipt/Invoice"><i class="fas fa-paperclip"></i></a>'
                    : '';
                $inspectionBtn = $record->technical_inspection_path
                    ? '<a href="' . route('repairs.document', ['path' => $record->technical_inspection_path]) . '" target="_blank" class="btn btn-sm btn-light border text-secondary" title="View Technical Inspection Report"><i class="fas fa-clipboard-check"></i></a>'
                    : '';
                // Motorpool Service Request Form view link — newly relevant to this
                // module now that a repair also starts with the same digitized form
                // (VMIS process-flow redesign, Step 1 of Repair or Maintenance alike).
                $serviceRequestBtn = $record->service_request_path
                    ? '<a href="' . route('repairs.document', ['path' => $record->service_request_path]) . '" target="_blank" class="btn btn-sm btn-light border text-secondary" title="View Motorpool Service Request Form"><i class="fas fa-file-invoice"></i></a>'
                    : '';
                // Scanned copy of the signed Requisition Slip, if one's been
                // attached — purely a supporting-document view link now (see
                // below for the digitized slip itself, which is what actually
                // gates completion). See the matching comment in
                // MaintenanceController::index() for why this split exists.
                $requisitionScanBtn = $record->requisition_slip_path
                    ? '<a href="' . route('repairs.document', ['path' => $record->requisition_slip_path]) . '" target="_blank" class="btn btn-sm btn-light border text-secondary" title="View scanned Repair Requisition Slip"><i class="fas fa-boxes"></i></a>'
                    : '';
                // The digitized Requisition Slip — shown whenever the checklist has
                // flagged parts as needed, amber until it's actually been filled out
                // with at least one item (MaintenanceRecord::hasRequisitionFilled()),
                // since that's now what canComplete() checks.
                $requisitionBtn = '';
                if ($record->canFillRequisition()) {
                    $filled = $record->hasRequisitionFilled();
                    $requisitionBtn = '<button type="button" class="btn btn-sm ' . ($filled ? 'btn-light border text-info' : 'btn-warning') . ' btn-requisition-slip" data-id="' . $record->id . '" title="' . ($filled ? 'Vehicle Repair Requisition Slip' : 'Parts flagged as needed — click to fill out the Requisition Slip') . '"><i class="fas fa-receipt"></i></button>';
                }
                // The digitized checklist (VMIS Additional Updates follow-up: predictive
                // maintenance) — separate from the PDF-view button above, and shown to
                // every role including Viewer (read-only) since it now holds real
                // per-part history, not just an attachment link.
                $checklistBtn = '<button type="button" class="btn btn-sm btn-light border text-info btn-inspection-checklist" data-id="' . $record->id . '" title="Technical Inspection Checklist"><i class="fas fa-diagnoses"></i></button>';

                // Process-flow gate: the "Complete Service" action only appears while
                // there's something left to complete, and is disabled (with the exact
                // reason as its tooltip) until MaintenanceRecord::canComplete() allows it.
                $completeBtn = '';
                if ($record->stage !== MaintenanceRecord::STAGE_COMPLETED) {
                    $completeBtn = $record->canComplete()
                        ? '<button type="button" class="btn btn-sm btn-success btn-complete-repair" data-id="' . $record->id . '" title="Complete Service"><i class="fas fa-check-circle"></i></button>'
                        : '<button type="button" class="btn btn-sm btn-light border text-muted" disabled title="' . e($record->blockedCompletionReason()) . '"><i class="fas fa-check-circle"></i></button>';
                }

                if ($isViewer) {
                    $actionsHtml = '<div class="btn-group" role="group">' . $attachmentBtn . $inspectionBtn . $serviceRequestBtn . $requisitionScanBtn . $checklistBtn . $requisitionBtn . '</div>';
                } else {
                    $actionsHtml = '
                        <div class="btn-group" role="group">
                            ' . $attachmentBtn . $inspectionBtn . $serviceRequestBtn . $requisitionScanBtn . $checklistBtn . $requisitionBtn . $completeBtn . '
                            <button type="button" class="btn btn-sm btn-light border text-primary btn-edit-repair" data-id="' . $record->id . '" title="Edit"><i class="fas fa-edit"></i></button>
                            <button type="button" class="btn btn-sm btn-light border text-danger btn-delete-repair" data-id="' . $record->id . '" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>';
                }

                $data[] = [
                    'vehicle_html' => $vehicleHtml,
                    'stage_html'   => $stageHtml,
                    'desc_html'    => $descHtml,
                    'service_html' => $serviceHtml,
                    'cost_html'    => $costHtml,
                    'shop_html'    => $shopHtml,
                    'logged_html'  => $loggedHtml,
                    'actions_html' => $actionsHtml,
                ];
            }

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $data,
                'stats'           => $stats,
            ]);
        }

        // ---------------- Initial (non-AJAX) page load ----------------
        $vehicleScope = Vehicle::query();
        $this->scopeToVisibleVehicles($vehicleScope, $user, $role);
        $vehicles = (clone $vehicleScope)->orderBy('plate_number')->get(['id', 'plate_number', 'make', 'model', 'odometer_km']);

        $recordScope = MaintenanceRecord::query()->repairsOnly();
        $this->scopeToVisibleVehicles($recordScope, $user, $role, viaRelation: true);

        $stats = [
            'total_records'   => (clone $recordScope)->count(),
            'this_month'      => (clone $recordScope)->completedOnly()->whereBetween('service_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'this_month_cost' => (clone $recordScope)->completedOnly()->whereBetween('service_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('cost'),
            'this_year_cost'  => (clone $recordScope)->completedOnly()->whereBetween('service_date', [now()->startOfYear(), now()->endOfYear()])->sum('cost'),
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

        $aiDocumentScanningEnabled = app(DocumentIntelligenceService::class)->isConfigured();

        return view('repairs.index', compact(
            'stats', 'vehicles', 'units', 'stations', 'hasBroadVisibility', 'isViewer', 'aiDocumentScanningEnabled'
        ));
    }

    /**
     * Opens a new repair request for a vehicle — the digitized Motorpool
     * Service Request Form (Step 1 of the process flow, same as Maintenance &
     * PMS — see MaintenanceController::store()'s docblock for the full
     * rationale, mirrored here). maintenance_type/nature_of_request are never
     * taken from the request: this module only ever writes REPAIR rows, and
     * "Repair / Other Concern" is the only nature_of_request that applies.
     * No longer accepts service_date/odometer/cost/performed_by/attachment —
     * those belong to completeService() below, gated behind the Technical
     * Inspection (and Requisition Slip, if parts are needed) being filled in
     * first.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id'            => ['required', 'exists:vehicles,id'],
            'description'           => ['nullable', 'string', 'max:1000'],
            'request_date'          => ['required', 'date'],
            'requested_by'          => ['required', 'string', 'max:150'],
            'recommended_by'        => ['nullable', 'string', 'max:150'],
            'approved_by'           => ['nullable', 'string', 'max:150'],
            // Official PRO5/RLRDD forms — filled-out/scanned copies, optional
            // alongside the structured fields above.
            'technical_inspection'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'service_request_form'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $this->authorizeVehicleWrite($vehicle);

        $validated = $this->applyFormAttachment($request, $validated, null, 'technical_inspection', 'remove_technical_inspection', 'technical_inspection_path', 'repair_docs/inspection');
        $validated = $this->applyFormAttachment($request, $validated, null, 'service_request_form', 'remove_service_request_form', 'service_request_path', 'repair_docs/service_request');

        $validated['maintenance_type'] = 'REPAIR';
        $validated['nature_of_request'] = 'REPAIR_OTHER';
        $validated['stage'] = MaintenanceRecord::STAGE_REQUESTED;
        $validated['service_date'] = $validated['request_date'];
        $validated['recorded_by'] = auth()->id();

        $record = MaintenanceRecord::create($validated);
        // Needs the row's own auto-increment id, so it's a second lightweight
        // save right after create() rather than something guessable up front.
        $record->update(['control_number' => 'SR-' . str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)]);

        // Deliberately NOT calling syncVehicleFromLatestRecord() here — same
        // reasoning as MaintenanceController::store(): this record has no
        // real next_due_date/odometer_km yet, and that method only ever
        // looks at Completed rows anyway. It runs for real in completeService().

        ActivityLog::record(
            'created',
            'Repair Record',
            'Requested a repair for [' . strtoupper($vehicle->plate_number) . '] (' . $record->control_number . ').',
            $record,
            ['after' => Arr::except($validated, ['technical_inspection', 'service_request_form'])]
        );

        return response()->json([
            'success' => true,
            'message' => 'Repair request ' . $record->control_number . ' logged for [' . strtoupper($vehicle->plate_number) . ']. Fill out the Technical Inspection next.',
        ]);
    }

    /**
     * Raw field values used to populate the Edit modal via AJAX.
     */
    public function editData(MaintenanceRecord $repair): JsonResponse
    {
        $this->authorizeVehicleWrite($repair->vehicle);
        // A Maintenance & PMS record should never be reachable through the
        // Repairs module, even by guessing an id.
        abort_if($repair->maintenance_type !== 'REPAIR', 404);

        return response()->json([
            'id'                    => $repair->id,
            'vehicle_id'            => $repair->vehicle_id,
            'vehicle_label'         => strtoupper($repair->vehicle->plate_number) . ' — ' . trim($repair->vehicle->make . ' ' . $repair->vehicle->model),
            'description'           => $repair->description,
            'stage'                 => $repair->stage,
            'stage_label'           => MaintenanceRecord::STAGES[$repair->stage] ?? $repair->stage,
            'stage_color'           => MaintenanceRecord::STAGE_COLORS[$repair->stage] ?? 'secondary',
            'control_number'        => $repair->control_number,
            'request_date'          => optional($repair->request_date)->format('Y-m-d'),
            'requested_by'          => $repair->requested_by,
            'recommended_by'        => $repair->recommended_by,
            'approved_by'           => $repair->approved_by,
            'parts_needed'          => (bool) $repair->parts_needed,
            'can_complete'          => $repair->canComplete(),
            'can_fill_requisition'  => $repair->canFillRequisition(),
            'blocked_reason'        => $repair->blockedCompletionReason(),
            'service_date'   => optional($repair->service_date)->format('Y-m-d'),
            'odometer_km'    => $repair->odometer_km,
            'cost'           => $repair->cost,
            'performed_by'   => $repair->performed_by,
            'received_by'    => $repair->received_by,
            'has_attachment' => (bool) $repair->attachment_path,
            'attachment_url' => $repair->attachment_path ? route('repairs.document', ['path' => $repair->attachment_path]) : null,
            'has_technical_inspection' => (bool) $repair->technical_inspection_path,
            'technical_inspection_url' => $repair->technical_inspection_path ? route('repairs.document', ['path' => $repair->technical_inspection_path]) : null,
            'has_service_request'     => (bool) $repair->service_request_path,
            'service_request_url'     => $repair->service_request_path ? route('repairs.document', ['path' => $repair->service_request_path]) : null,
            'has_requisition_slip'     => (bool) $repair->requisition_slip_path,
            'requisition_slip_url'     => $repair->requisition_slip_path ? route('repairs.document', ['path' => $repair->requisition_slip_path]) : null,
        ]);
    }

    /**
     * Update an existing repair record. Sent as POST + _method=PUT for the same
     * reason as MaintenanceController::update() — real multipart file upload
     * support for a replacement attachment.
     */
    public function update(Request $request, MaintenanceRecord $repair): JsonResponse
    {
        $this->authorizeVehicleWrite($repair->vehicle);
        abort_if($repair->maintenance_type !== 'REPAIR', 404);

        $validated = $request->validate([
            'vehicle_id'         => ['required', 'exists:vehicles,id'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'request_date'       => ['nullable', 'date'],
            'requested_by'       => ['nullable', 'string', 'max:150'],
            'recommended_by'     => ['nullable', 'string', 'max:150'],
            'approved_by'        => ['nullable', 'string', 'max:150'],
            'service_date'       => ['required', 'date'],
            'odometer_km'        => ['nullable', 'integer', 'min:0'],
            'cost'               => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'performed_by'       => ['nullable', 'string', 'max:150'],
            'received_by'        => ['nullable', 'string', 'max:150'],
            'attachment'         => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_attachment'  => ['nullable', 'boolean'],
            'technical_inspection' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_technical_inspection' => ['nullable', 'boolean'],
            'service_request_form'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_service_request_form' => ['nullable', 'boolean'],
            'requisition_slip'     => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_requisition_slip' => ['nullable', 'boolean'],
        ]);

        $newVehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $this->authorizeVehicleWrite($newVehicle);

        $oldVehicle = $repair->vehicle;

        if ($request->hasFile('attachment')) {
            if ($repair->attachment_path) {
                Storage::disk('public')->delete($repair->attachment_path);
            }
            $validated['attachment_path'] = $request->file('attachment')->store('repair_docs', 'public');
        } elseif ($request->boolean('remove_attachment') && $repair->attachment_path) {
            Storage::disk('public')->delete($repair->attachment_path);
            $validated['attachment_path'] = null;
        }
        unset($validated['remove_attachment']);
        $validated = $this->applyFormAttachment($request, $validated, $repair->technical_inspection_path, 'technical_inspection', 'remove_technical_inspection', 'technical_inspection_path', 'repair_docs/inspection');
        $validated = $this->applyFormAttachment($request, $validated, $repair->service_request_path, 'service_request_form', 'remove_service_request_form', 'service_request_path', 'repair_docs/service_request');

        // Process-flow gate: the Requisition Slip can only be filled in once the
        // Technical Inspection has flagged that parts are needed — see
        // MaintenanceRecord::canFillRequisition(). Silently skipped rather than
        // erroring the whole update if it isn't allowed yet, matching how the
        // field is also hidden/disabled client-side (and how
        // MaintenanceController::update() now gates the same field).
        if ($repair->canFillRequisition()) {
            $validated = $this->applyFormAttachment($request, $validated, $repair->requisition_slip_path, 'requisition_slip', 'remove_requisition_slip', 'requisition_slip_path', 'repair_docs/requisition');
        }
        // Always REPAIR — this module can never turn a record into a different type.
        $validated['maintenance_type'] = 'REPAIR';

        $before = $repair->getOriginal();
        $repair->update($validated);
        $changed = $repair->getChanges();
        unset($changed['updated_at']);

        $this->syncVehicleFromLatestRecord($newVehicle);
        if ($oldVehicle && $oldVehicle->id !== $newVehicle->id) {
            $this->syncVehicleFromLatestRecord($oldVehicle);
        }

        if (!empty($changed)) {
            ActivityLog::record(
                'updated',
                'Repair Record',
                'Updated repair record for [' . strtoupper($newVehicle->plate_number) . '].',
                $repair,
                ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Repair record updated successfully.',
        ]);
    }

    /**
     * Step 4 of the process flow: marks a repair Completed by filling in the
     * actual service details — identical in shape to
     * MaintenanceController::completeService(), gated the same way behind
     * MaintenanceRecord::canComplete(). Also where Part IV "Certification of
     * Completion" (received_by) gets recorded and the three official form
     * scans can be attached as supporting documents — see the matching
     * docblock on MaintenanceController::completeService().
     */
    public function completeService(Request $request, MaintenanceRecord $repair): JsonResponse
    {
        $this->authorizeVehicleWrite($repair->vehicle);
        abort_if($repair->maintenance_type !== 'REPAIR', 404);
        abort_unless($repair->canComplete(), 422, $repair->blockedCompletionReason() ?? 'This record cannot be completed yet.');

        $validated = $request->validate([
            'service_date'          => ['required', 'date'],
            'odometer_km'           => ['nullable', 'integer', 'min:0'],
            'cost'                  => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'performed_by'          => ['required', 'string', 'max:150'],
            'received_by'           => ['required', 'string', 'max:150'],
            'attachment'            => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'technical_inspection'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_technical_inspection' => ['nullable', 'boolean'],
            'service_request_form'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_service_request_form' => ['nullable', 'boolean'],
            'requisition_slip'      => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_requisition_slip' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('attachment')) {
            if ($repair->attachment_path) {
                Storage::disk('public')->delete($repair->attachment_path);
            }
            $validated['attachment_path'] = $request->file('attachment')->store('repair_docs', 'public');
        }
        $validated = $this->applyFormAttachment($request, $validated, $repair->technical_inspection_path, 'technical_inspection', 'remove_technical_inspection', 'technical_inspection_path', 'repair_docs/inspection');
        $validated = $this->applyFormAttachment($request, $validated, $repair->service_request_path, 'service_request_form', 'remove_service_request_form', 'service_request_path', 'repair_docs/service_request');
        if ($repair->canFillRequisition()) {
            $validated = $this->applyFormAttachment($request, $validated, $repair->requisition_slip_path, 'requisition_slip', 'remove_requisition_slip', 'requisition_slip_path', 'repair_docs/requisition');
        }
        $validated['stage'] = MaintenanceRecord::STAGE_COMPLETED;

        $before = $repair->getOriginal();
        $repair->update($validated);
        $changed = $repair->getChanges();
        unset($changed['updated_at']);

        $this->syncVehicleFromLatestRecord($repair->vehicle);

        ActivityLog::record(
            'updated',
            'Repair Record',
            'Completed repair for [' . strtoupper($repair->vehicle->plate_number) . ']' . ($repair->control_number ? ' (' . $repair->control_number . ')' : '') . '.',
            $repair,
            ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
        );

        return response()->json([
            'success' => true,
            'message' => 'Repair completed for [' . strtoupper($repair->vehicle->plate_number) . '].',
        ]);
    }

    public function destroy(MaintenanceRecord $repair): JsonResponse
    {
        $this->authorizeVehicleWrite($repair->vehicle);
        abort_if($repair->maintenance_type !== 'REPAIR', 404);

        $vehicle = $repair->vehicle;
        $snapshot = $repair->toArray();
        $plateLabel = $vehicle ? strtoupper($vehicle->plate_number) : 'unknown vehicle';

        if ($repair->attachment_path) {
            Storage::disk('public')->delete($repair->attachment_path);
        }
        if ($repair->technical_inspection_path) {
            Storage::disk('public')->delete($repair->technical_inspection_path);
        }
        if ($repair->service_request_path) {
            Storage::disk('public')->delete($repair->service_request_path);
        }
        if ($repair->requisition_slip_path) {
            Storage::disk('public')->delete($repair->requisition_slip_path);
        }
        $repair->delete();

        if ($vehicle) {
            $this->syncVehicleFromLatestRecord($vehicle);
        }

        ActivityLog::record(
            'deleted',
            'Repair Record',
            'Deleted repair record for [' . $plateLabel . '].',
            $repair,
            ['before' => $snapshot]
        );

        return response()->json([
            'success' => true,
            'message' => 'Repair record deleted.',
        ]);
    }

    /**
     * Serves an uploaded repair receipt/invoice directly from the private storage
     * disk — same rationale as MaintenanceController::viewDocument.
     */
    public function viewDocument(string $path)
    {
        if (! str_starts_with($path, 'repair_docs/') || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }
}
