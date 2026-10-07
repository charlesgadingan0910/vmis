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
     * COMPLETED maintenance record now says, after any create/update/delete/completion.
     * Only Completed rows count — a Requested/Inspected/Awaiting-Parts record's
     * service_date is just a request_date placeholder and its next_due_date is still
     * empty (see the stage-workflow migration), so including those would blank out a
     * vehicle's real PMS schedule the moment someone opens a new, unfinished request.
     */
    protected function syncVehicleFromLatestRecord(Vehicle $vehicle): void
    {
        $latest = MaintenanceRecord::where('vehicle_id', $vehicle->id)
            ->completedOnly()
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
     * Stores, replaces, or removes one of the named form-attachment slots
     * (Technical Inspection Report, Motorpool Service Request Form, Vehicle
     * Repair Requisition Slip) exactly like the receipt/invoice attachment —
     * just parameterized so the same logic covers both store() (existingPath
     * is null) and update() (existingPath is whatever's already on the record).
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

        // Same reasoning as VehicleController::index() — scopeToVisibleVehicles()
        // has no branch for DRIVER, which would otherwise leave maintenance
        // records completely unfiltered for it. A driver has no reason to be
        // on this page; PMS/maintenance history isn't part of their job.
        if ($role === 'DRIVER') {
            abort(403, 'Driver accounts do not have access to Maintenance & PMS.');
        }

        $hasBroadVisibility = in_array($role, self::BROAD_VISIBILITY_ROLES, true);
        $isViewer = ($role === self::ROLE_VIEWER);

        if ($request->ajax()) {
            // Repairs moved to their own module (RepairController) — same
            // table, just partitioned by type, so this listing simply leaves
            // REPAIR rows out rather than needing a migration.
            $query = MaintenanceRecord::with(['vehicle.type', 'recorder'])
                ->excludingRepairs()
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
                      ->orWhere('control_number', 'like', "%{$search}%")
                      ->orWhereHas('vehicle', function ($vq) use ($search) {
                          $vq->where('plate_number', 'like', "%{$search}%")
                             ->orWhere('make', 'like', "%{$search}%")
                             ->orWhere('model', 'like', "%{$search}%");
                      });
                });
            }

            $totalScoped = MaintenanceRecord::query()->excludingRepairs();
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
            // "Serviced This Month" only ever meant completed work — now that a
            // Requested/Inspected/Awaiting-Parts record also carries a service_date
            // (a request_date placeholder, not a real completion date), this has to
            // filter to completedOnly() or an in-flight request would inflate it.
            $recordsThisMonthScope = MaintenanceRecord::query()->excludingRepairs()->completedOnly()->whereBetween('service_date', [
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

                // Process-flow stage (VMIS process-flow redesign: Requested -> Inspected
                // -> Awaiting Parts -> Completed). See MaintenanceRecord::STAGE_* consts.
                $stageColor = MaintenanceRecord::STAGE_COLORS[$record->stage] ?? 'secondary';
                $stageLabel = MaintenanceRecord::STAGES[$record->stage] ?? $record->stage;
                $stageHtml = '<span class="badge badge-' . $stageColor . ' px-2 py-1" style="font-size:11px;font-weight:700;">' . e($stageLabel) . '</span>';
                if ($record->control_number) {
                    $stageHtml .= '<div class="vehicle-sub-info mt-1">' . e($record->control_number) . '</div>';
                }

                $typeColor = MaintenanceRecord::TYPE_COLORS[$record->maintenance_type] ?? 'light';
                $typeLabel = MaintenanceRecord::TYPES[$record->maintenance_type] ?? $record->maintenance_type;
                $typeHtml = '<span class="badge badge-' . $typeColor . ' px-2 py-1" style="font-size:11.5px;font-weight:700;">' . e($typeLabel) . '</span>';
                if ($record->description) {
                    $typeHtml .= '<div class="vehicle-sub-info mt-1">' . e(Str::limit($record->description, 60)) . '</div>';
                }

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

                $nextDueHtml = $this->nextDueHtml($record->next_due_date);
                if ($record->next_due_odometer_km) {
                    $nextDueHtml .= '<div class="vehicle-sub-info mt-1">at ' . number_format($record->next_due_odometer_km) . ' km</div>';
                }

                $loggedHtml = '<div class="vehicle-sub-info">' . e(optional($record->recorder)->fullname ?? 'System') . '</div>' .
                              '<div class="vehicle-sub-info">' . $record->created_at->format('M d, Y') . '</div>';

                $attachmentBtn = $record->attachment_path
                    ? '<a href="' . route('maintenance.document', ['path' => $record->attachment_path]) . '" target="_blank" class="btn btn-sm btn-light border text-secondary" title="View Receipt/Invoice"><i class="fas fa-paperclip"></i></a>'
                    : '';
                $inspectionBtn = $record->technical_inspection_path
                    ? '<a href="' . route('maintenance.document', ['path' => $record->technical_inspection_path]) . '" target="_blank" class="btn btn-sm btn-light border text-secondary" title="View Technical Inspection Report"><i class="fas fa-clipboard-check"></i></a>'
                    : '';
                $serviceRequestBtn = $record->service_request_path
                    ? '<a href="' . route('maintenance.document', ['path' => $record->service_request_path]) . '" target="_blank" class="btn btn-sm btn-light border text-secondary" title="View Motorpool Service Request Form"><i class="fas fa-file-invoice"></i></a>'
                    : '';
                // Scanned copy of the signed Requisition Slip, if one's been
                // attached — purely a supporting-document view link now (see
                // below for the digitized slip itself, which is what actually
                // gates completion).
                $requisitionScanBtn = $record->requisition_slip_path
                    ? '<a href="' . route('maintenance.document', ['path' => $record->requisition_slip_path]) . '" target="_blank" class="btn btn-sm btn-light border text-secondary" title="View scanned Repair Requisition Slip"><i class="fas fa-boxes"></i></a>'
                    : '';
                // The digitized Requisition Slip — shown whenever the checklist has
                // flagged parts as needed, amber (and with a nudging tooltip) until
                // it's actually been filled out with at least one item, since that's
                // now what MaintenanceRecord::canComplete() checks. Admin feedback on
                // an earlier version of this button (before it opened its own modal):
                // a silently-disabled Complete button alone didn't make the next step
                // obvious, so this still spells it out via title/color.
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
                        ? '<button type="button" class="btn btn-sm btn-success btn-complete-maintenance" data-id="' . $record->id . '" title="Complete Service"><i class="fas fa-check-circle"></i></button>'
                        : '<button type="button" class="btn btn-sm btn-light border text-muted" disabled title="' . e($record->blockedCompletionReason()) . '"><i class="fas fa-check-circle"></i></button>';
                }

                if ($isViewer) {
                    $actionsHtml = '<div class="btn-group" role="group">' . $attachmentBtn . $inspectionBtn . $serviceRequestBtn . $requisitionScanBtn . $checklistBtn . $requisitionBtn . '</div>';
                } else {
                    $actionsHtml = '
                        <div class="btn-group" role="group">
                            ' . $attachmentBtn . $inspectionBtn . $serviceRequestBtn . $requisitionScanBtn . $checklistBtn . $requisitionBtn . $completeBtn . '
                            <button type="button" class="btn btn-sm btn-light border text-primary btn-edit-maintenance" data-id="' . $record->id . '" title="Edit"><i class="fas fa-edit"></i></button>
                            <button type="button" class="btn btn-sm btn-light border text-danger btn-delete-maintenance" data-id="' . $record->id . '" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>';
                }

                $data[] = [
                    'vehicle_html'    => $vehicleHtml,
                    'stage_html'      => $stageHtml,
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

        $recordScope = MaintenanceRecord::query()->excludingRepairs();
        $this->scopeToVisibleVehicles($recordScope, $user, $role, viaRelation: true);

        $stats = [
            'total_records'  => (clone $recordScope)->count(),
            'due_soon'       => $dueSoonCount,
            'overdue'        => $overdueCount,
            'serviced_month' => (clone $recordScope)->completedOnly()->whereBetween('service_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
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

        // Controls whether the "auto-fill from receipt photo" hint/behavior is offered —
        // stays silent rather than showing a control that always fails when no AI vision
        // API key has been configured yet.
        $aiDocumentScanningEnabled = app(DocumentIntelligenceService::class)->isConfigured();

        return view('maintenance.index', compact(
            'stats', 'vehicles', 'units', 'stations', 'hasBroadVisibility', 'isViewer', 'aiDocumentScanningEnabled'
        ));
    }

    /**
     * Opens a new service request for a vehicle — the digitized Motorpool
     * Service Request Form (Step 1 of the process flow). This deliberately
     * does NOT accept service_date/cost/performed_by/next_due_date/attachment
     * any more — those only make sense once the job is actually done, and now
     * belong to completeService() below, gated behind the Technical
     * Inspection (and Requisition Slip, if parts are needed) being filled in
     * first. service_date is set to request_date as a required-NOT-NULL
     * placeholder (see the stage-workflow migration's docblock for why) and
     * gets overwritten with the real completion date in completeService().
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id'            => ['required', 'exists:vehicles,id'],
            // REPAIR is deliberately excluded — repairs are logged through
            // their own module (RepairController) now, not here.
            'maintenance_type'      => ['required', Rule::in(array_diff(array_keys(MaintenanceRecord::TYPES), ['REPAIR']))],
            'description'           => ['nullable', 'string', 'max:1000'],
            'request_date'          => ['required', 'date'],
            'requested_by'          => ['required', 'string', 'max:150'],
            'recommended_by'        => ['nullable', 'string', 'max:150'],
            'approved_by'           => ['nullable', 'string', 'max:150'],
            // Official PRO5/RLRDD forms — filled-out/scanned copies, optional
            // alongside the structured fields above. Technical Inspection
            // Report applies to any maintenance type; Motorpool Service
            // Request Form is specifically for Change Oil/PMS (the form's
            // own subtitle).
            'technical_inspection'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'service_request_form'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $this->authorizeVehicleWrite($vehicle);

        $validated = $this->applyFormAttachment($request, $validated, null, 'technical_inspection', 'remove_technical_inspection', 'technical_inspection_path', 'maintenance_docs/inspection');
        $validated = $this->applyFormAttachment($request, $validated, null, 'service_request_form', 'remove_service_request_form', 'service_request_path', 'maintenance_docs/service_request');

        $validated['nature_of_request'] = MaintenanceRecord::NATURE_OF_REQUEST_BY_TYPE[$validated['maintenance_type']] ?? 'REPAIR_OTHER';
        $validated['stage'] = MaintenanceRecord::STAGE_REQUESTED;
        $validated['service_date'] = $validated['request_date'];
        $validated['recorded_by'] = auth()->id();

        $record = MaintenanceRecord::create($validated);
        // Needs the row's own auto-increment id, so it's a second lightweight
        // save right after create() rather than something guessable up front.
        $record->update(['control_number' => 'SR-' . str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)]);

        // Deliberately NOT calling syncVehicleFromLatestRecord() here — this
        // record has no real next_due_date/odometer_km yet, and that method
        // only ever looks at Completed rows anyway (see its own docblock), so
        // it would be a no-op besides. It runs for real in completeService().

        ActivityLog::record(
            'created',
            'Maintenance Record',
            'Requested ' . (MaintenanceRecord::TYPES[$validated['maintenance_type']] ?? $validated['maintenance_type']) . ' for [' . strtoupper($vehicle->plate_number) . '] (' . $record->control_number . ').',
            $record,
            ['after' => Arr::except($validated, ['technical_inspection', 'service_request_form'])]
        );

        return response()->json([
            'success' => true,
            'message' => 'Service request ' . $record->control_number . ' logged for [' . strtoupper($vehicle->plate_number) . ']. Fill out the Technical Inspection next.',
        ]);
    }

    /**
     * Raw field values used to populate the Edit modal via AJAX.
     */
    public function editData(MaintenanceRecord $maintenance): JsonResponse
    {
        $this->authorizeVehicleWrite($maintenance->vehicle);
        // Repairs live in the Repairs module now — a Maintenance & PMS URL
        // should never be able to reach one, even by guessing an id.
        abort_if($maintenance->maintenance_type === 'REPAIR', 404);

        return response()->json([
            'id'                    => $maintenance->id,
            'vehicle_id'            => $maintenance->vehicle_id,
            'vehicle_label'         => strtoupper($maintenance->vehicle->plate_number) . ' — ' . trim($maintenance->vehicle->make . ' ' . $maintenance->vehicle->model),
            'maintenance_type'      => $maintenance->maintenance_type,
            'description'           => $maintenance->description,
            'stage'                 => $maintenance->stage,
            'stage_label'           => MaintenanceRecord::STAGES[$maintenance->stage] ?? $maintenance->stage,
            'stage_color'           => MaintenanceRecord::STAGE_COLORS[$maintenance->stage] ?? 'secondary',
            'control_number'        => $maintenance->control_number,
            'request_date'          => optional($maintenance->request_date)->format('Y-m-d'),
            'requested_by'          => $maintenance->requested_by,
            'recommended_by'        => $maintenance->recommended_by,
            'approved_by'           => $maintenance->approved_by,
            'parts_needed'          => (bool) $maintenance->parts_needed,
            'can_complete'          => $maintenance->canComplete(),
            'can_fill_requisition'  => $maintenance->canFillRequisition(),
            'blocked_reason'        => $maintenance->blockedCompletionReason(),
            'service_date'          => optional($maintenance->service_date)->format('Y-m-d'),
            'odometer_km'           => $maintenance->odometer_km,
            'cost'                  => $maintenance->cost,
            'performed_by'          => $maintenance->performed_by,
            'received_by'           => $maintenance->received_by,
            'next_due_date'         => optional($maintenance->next_due_date)->format('Y-m-d'),
            'next_due_odometer_km'  => $maintenance->next_due_odometer_km,
            'has_attachment'        => (bool) $maintenance->attachment_path,
            'attachment_url'        => $maintenance->attachment_path ? route('maintenance.document', ['path' => $maintenance->attachment_path]) : null,
            'has_technical_inspection' => (bool) $maintenance->technical_inspection_path,
            'technical_inspection_url' => $maintenance->technical_inspection_path ? route('maintenance.document', ['path' => $maintenance->technical_inspection_path]) : null,
            'has_service_request'     => (bool) $maintenance->service_request_path,
            'service_request_url'     => $maintenance->service_request_path ? route('maintenance.document', ['path' => $maintenance->service_request_path]) : null,
            'has_requisition_slip'    => (bool) $maintenance->requisition_slip_path,
            'requisition_slip_url'    => $maintenance->requisition_slip_path ? route('maintenance.document', ['path' => $maintenance->requisition_slip_path]) : null,
        ]);
    }

    /**
     * Update an existing record. Sent as POST + _method=PUT (not a plain PUT) because a
     * replacement attachment needs real multipart file upload support — PHP never
     * populates $_FILES for a native PUT request, spoofed POST is the standard workaround.
     * Stays the general-purpose "fix anything on this record" action regardless of stage
     * (unlike completeService(), it's never gated) — the request-stage fields
     * (requested_by, etc.) and completion fields (cost, etc.) are all nullable here so
     * editing a record doesn't force filling in details that stage hasn't reached yet,
     * and so older, pre-workflow Completed records (which never had requested_by/
     * request_date to begin with) can still be edited without those.
     */
    public function update(Request $request, MaintenanceRecord $maintenance): JsonResponse
    {
        $this->authorizeVehicleWrite($maintenance->vehicle);
        abort_if($maintenance->maintenance_type === 'REPAIR', 404);

        $validated = $request->validate([
            'vehicle_id'            => ['required', 'exists:vehicles,id'],
            'maintenance_type'      => ['required', Rule::in(array_diff(array_keys(MaintenanceRecord::TYPES), ['REPAIR']))],
            'description'           => ['nullable', 'string', 'max:1000'],
            'request_date'          => ['nullable', 'date'],
            'requested_by'          => ['nullable', 'string', 'max:150'],
            'recommended_by'        => ['nullable', 'string', 'max:150'],
            'approved_by'           => ['nullable', 'string', 'max:150'],
            'service_date'          => ['required', 'date'],
            'odometer_km'           => ['nullable', 'integer', 'min:0'],
            'cost'                  => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'performed_by'          => ['nullable', 'string', 'max:150'],
            'received_by'           => ['nullable', 'string', 'max:150'],
            'next_due_date'         => ['nullable', 'date', 'after_or_equal:service_date'],
            'next_due_odometer_km'  => ['nullable', 'integer', 'min:0'],
            'attachment'            => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_attachment'     => ['nullable', 'boolean'],
            'technical_inspection'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_technical_inspection' => ['nullable', 'boolean'],
            'service_request_form'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_service_request_form' => ['nullable', 'boolean'],
            'requisition_slip'      => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_requisition_slip' => ['nullable', 'boolean'],
        ]);

        $newVehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $this->authorizeVehicleWrite($newVehicle);

        $oldVehicle = $maintenance->vehicle;

        $validated['nature_of_request'] = MaintenanceRecord::NATURE_OF_REQUEST_BY_TYPE[$validated['maintenance_type']] ?? 'REPAIR_OTHER';

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
        $validated = $this->applyFormAttachment($request, $validated, $maintenance->technical_inspection_path, 'technical_inspection', 'remove_technical_inspection', 'technical_inspection_path', 'maintenance_docs/inspection');
        $validated = $this->applyFormAttachment($request, $validated, $maintenance->service_request_path, 'service_request_form', 'remove_service_request_form', 'service_request_path', 'maintenance_docs/service_request');

        // Process-flow gate: the Requisition Slip can only be filled in once the
        // Technical Inspection has flagged that parts are needed — see
        // MaintenanceRecord::canFillRequisition(). Silently skipped rather than
        // erroring the whole update if it isn't allowed yet, matching how the
        // field is also hidden/disabled client-side.
        if ($maintenance->canFillRequisition()) {
            $validated = $this->applyFormAttachment($request, $validated, $maintenance->requisition_slip_path, 'requisition_slip', 'remove_requisition_slip', 'requisition_slip_path', 'maintenance_docs/requisition');
        }

        $before = $maintenance->getOriginal();
        $maintenance->update($validated);
        $changed = $maintenance->getChanges();
        unset($changed['updated_at']);

        // Re-sync whichever vehicle(s) could be affected — both the old one (if this
        // record was moved off it) and the new/current one.
        $this->syncVehicleFromLatestRecord($newVehicle);
        if ($oldVehicle && $oldVehicle->id !== $newVehicle->id) {
            $this->syncVehicleFromLatestRecord($oldVehicle);
        }

        if (!empty($changed)) {
            ActivityLog::record(
                'updated',
                'Maintenance Record',
                'Updated maintenance record for [' . strtoupper($newVehicle->plate_number) . '].',
                $maintenance,
                ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Maintenance record updated successfully.',
        ]);
    }

    /**
     * Step 4 of the process flow: marks a record Completed by filling in the
     * actual service details (cost, performed_by, odometer, receipt, next
     * schedule) — the fields store() used to collect in one shot before this
     * workflow existed. Blocked until MaintenanceRecord::canComplete() says
     * the record has been through the Technical Inspection (and the digitized
     * Requisition Slip too, if parts were flagged as needed). Also where Part
     * IV "Certification of Completion" (received_by) gets recorded, and where
     * the three official form scans (Technical Inspection, Motorpool Service
     * Request Form, Requisition Slip) are attached as supporting documents —
     * they can still be uploaded earlier via store()/update(), but this is
     * the step the process flow actually points admins to.
     */
    public function completeService(Request $request, MaintenanceRecord $maintenance): JsonResponse
    {
        $this->authorizeVehicleWrite($maintenance->vehicle);
        abort_if($maintenance->maintenance_type === 'REPAIR', 404);
        abort_unless($maintenance->canComplete(), 422, $maintenance->blockedCompletionReason() ?? 'This record cannot be completed yet.');

        $validated = $request->validate([
            'service_date'          => ['required', 'date'],
            'odometer_km'           => ['nullable', 'integer', 'min:0'],
            'cost'                  => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'performed_by'          => ['required', 'string', 'max:150'],
            // Part IV "Certification of Completion" on the Motorpool Service
            // Request Form — "Inspected and Received by".
            'received_by'           => ['required', 'string', 'max:150'],
            'next_due_date'         => ['nullable', 'date', 'after_or_equal:service_date'],
            'next_due_odometer_km'  => ['nullable', 'integer', 'min:0'],
            'attachment'            => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'technical_inspection'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_technical_inspection' => ['nullable', 'boolean'],
            'service_request_form'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_service_request_form' => ['nullable', 'boolean'],
            'requisition_slip'      => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_requisition_slip' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('attachment')) {
            if ($maintenance->attachment_path) {
                Storage::disk('public')->delete($maintenance->attachment_path);
            }
            $validated['attachment_path'] = $request->file('attachment')->store('maintenance_docs', 'public');
        }
        $validated = $this->applyFormAttachment($request, $validated, $maintenance->technical_inspection_path, 'technical_inspection', 'remove_technical_inspection', 'technical_inspection_path', 'maintenance_docs/inspection');
        $validated = $this->applyFormAttachment($request, $validated, $maintenance->service_request_path, 'service_request_form', 'remove_service_request_form', 'service_request_path', 'maintenance_docs/service_request');
        if ($maintenance->canFillRequisition()) {
            $validated = $this->applyFormAttachment($request, $validated, $maintenance->requisition_slip_path, 'requisition_slip', 'remove_requisition_slip', 'requisition_slip_path', 'maintenance_docs/requisition');
        }
        $validated['stage'] = MaintenanceRecord::STAGE_COMPLETED;

        $before = $maintenance->getOriginal();
        $maintenance->update($validated);
        $changed = $maintenance->getChanges();
        unset($changed['updated_at']);

        $this->syncVehicleFromLatestRecord($maintenance->vehicle);

        ActivityLog::record(
            'updated',
            'Maintenance Record',
            'Completed service for [' . strtoupper($maintenance->vehicle->plate_number) . ']' . ($maintenance->control_number ? ' (' . $maintenance->control_number . ')' : '') . '.',
            $maintenance,
            ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
        );

        return response()->json([
            'success' => true,
            'message' => 'Service completed for [' . strtoupper($maintenance->vehicle->plate_number) . '].',
        ]);
    }

    public function destroy(MaintenanceRecord $maintenance): JsonResponse
    {
        $this->authorizeVehicleWrite($maintenance->vehicle);
        abort_if($maintenance->maintenance_type === 'REPAIR', 404);

        $vehicle = $maintenance->vehicle;
        $snapshot = $maintenance->toArray();
        $plateLabel = $vehicle ? strtoupper($vehicle->plate_number) : 'unknown vehicle';

        if ($maintenance->attachment_path) {
            Storage::disk('public')->delete($maintenance->attachment_path);
        }
        if ($maintenance->technical_inspection_path) {
            Storage::disk('public')->delete($maintenance->technical_inspection_path);
        }
        if ($maintenance->service_request_path) {
            Storage::disk('public')->delete($maintenance->service_request_path);
        }
        if ($maintenance->requisition_slip_path) {
            Storage::disk('public')->delete($maintenance->requisition_slip_path);
        }
        $maintenance->delete();

        if ($vehicle) {
            $this->syncVehicleFromLatestRecord($vehicle);
        }

        ActivityLog::record(
            'deleted',
            'Maintenance Record',
            'Deleted maintenance record for [' . $plateLabel . '].',
            $maintenance,
            ['before' => $snapshot]
        );

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
