<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\VehicleType;
use App\Models\VehicleQrPrint;
use App\Models\Unit;
use App\Models\Station;
use App\Models\MaintenanceRecord;
use App\Models\TripLog;
use App\Models\FuelLog;
use App\Models\VehicleAccident;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Services\DocumentIntelligenceService;
use App\Services\PmsPredictionService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class VehicleController extends Controller
{
    // account_types.type stores these exact text labels (see account_types.sql) —
    // account_type on the users table is a STRING matching one of these, not a numeric id.
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';

    // Roles that see every unit/station/vehicle, not just their own slice of it.
    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    /**
     * Normalizes account_type for comparison — trims stray whitespace and
     * standardizes casing, so "Super Administrator" or " SUPER ADMINISTRATOR "
     * still matches the constants above.
     */
    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Small colored tag appended under an overdue/due-soon PMS badge, showing
     * PmsPredictionService's explainable urgency label with its full reasoning
     * in the tooltip — the "predictive" layer on top of the flat day-count
     * badge above it.
     */
    protected function pmsPriorityHtml(Vehicle $vehicle): string
    {
        $score = app(PmsPredictionService::class)->scoreVehicle($vehicle);
        if (! $score) {
            return '';
        }

        return '<div class="pms-priority-tag pms-priority-' . $score['priority'] . '" title="' . e($score['reason']) . '">' .
               '<i class="fas fa-bolt"></i> ' . ucfirst($score['priority']) . ' priority' .
               '</div>';
    }

    /**
     * Display vehicle inventory with dynamic filters & summary metrics.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->role($user);

        // DRIVER is a brand-new, deliberately narrow account type — none of
        // the unit/station scoping branches below recognize it, which would
        // otherwise fall through to an entirely UNFILTERED fleet-wide query.
        // A driver has no reason to browse the fleet inventory anyway; they
        // log trips for their own assigned vehicle via Trip Logs instead.
        if ($role === 'DRIVER') {
            abort(403, 'Driver accounts do not have access to Vehicle Inventory. Use Trip Logs to log your trips.');
        }

        $hasBroadVisibility = in_array($role, self::BROAD_VISIBILITY_ROLES, true);

        if ($request->ajax()) {
            $query = Vehicle::with(['driver', 'type', 'latestRegistration', 'encoder'])->latest();

            if ($hasBroadVisibility) {
                if ($unitId = $request->get('unit_id')) {
                    $query->where('unit_id', $unitId);
                }
            } elseif ($role === self::ROLE_UNIT_ADMIN) {
                $query->where('unit_id', $user->unit_id);
            } elseif ($role === self::ROLE_STATION_ADMIN) {
                $query->where('station_id', $user->station_id);
            }

            if ($stationId = $request->get('station_id')) {
                $query->where('station_id', $stationId);
            }

            if ($search = trim($request->input('search.value'))) {
                $query->where(function ($q) use ($search) {
                    $q->where('plate_number', 'like', "%{$search}%")
                      ->orWhere('make', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('engine_number', 'like', "%{$search}%")
                      ->orWhere('chassis_number', 'like', "%{$search}%");
                });
            }

            $statusFilter = $request->get('status');

            // "Vehicles by Type" breakdown: snapshot before the type filter is applied, so
            // every type's count shows for the current unit/station/search/status scope —
            // not just whichever single type happens to be selected in the filter.
            // .toBase() drops down to a plain query builder — cloning the Eloquent builder's
            // with() eager-loads and then running a raw grouped aggregate on it conflicts,
            // since eager-loading expects a normal row-per-model result, not grouped totals.
            $typeBreakdownBase = (clone $query)->toBase()->reorder();
            if ($statusFilter) {
                $typeBreakdownBase->where('status', $statusFilter);
            }
            $typeCounts = (clone $typeBreakdownBase)
                ->selectRaw('vehicle_type_id, count(*) as total')
                ->groupBy('vehicle_type_id')
                ->pluck('total', 'vehicle_type_id');

            $typeBreakdown = VehicleType::orderBy('name')->get()->map(function ($type) use ($typeCounts) {
                return [
                    'id'    => $type->id,
                    'name'  => $type->name,
                    'count' => (int) ($typeCounts[$type->id] ?? 0),
                ];
            });

            if ($typeId = $request->get('vehicle_type_id')) {
                $query->where('vehicle_type_id', $typeId);
            }

            if ($sourceFilter = $request->get('source')) {
                $query->where('source', $sourceFilter);
            }

            // Live stat cards: reflect unit/station/search/type filters, but deliberately
            // NOT the status filter itself — otherwise picking "Serviceable" would collapse
            // the other three cards to near-zero, which is accurate but not useful. This way
            // the cards always show the real breakdown for whatever scope is currently active.
            $statsQuery = (clone $query)->toBase();
            $stats = [
                // Excludes vehicles that are BER + Disposed — they stay in the
                // inventory listing for the record, but no longer count toward
                // the fleet total (see Vehicle::scopeExcludingDisposed).
                'total'         => (clone $statsQuery)->where(function ($q) {
                    $q->where('status', '!=', 'BER')
                      ->orWhereNull('ber_sub_status')
                      ->orWhere('ber_sub_status', '!=', 'DISPOSED');
                })->count(),
                'serviceable'   => (clone $statsQuery)->where('status', 'SERVICEABLE')->count(),
                'unserviceable' => (clone $statsQuery)->where('status', 'UNSERVICEABLE')->count(),
                'ber'           => (clone $statsQuery)->where('status', 'BER')->count(),
                // Unserviceable for 90+ days — .toBase() drops Vehicle's own scope
                // methods, so the equivalent where() clause is written out inline.
                'unserviceable_alert' => (clone $statsQuery)
                    ->where('status', 'UNSERVICEABLE')
                    ->whereNotNull('unserviceable_since')
                    ->where('unserviceable_since', '<=', now()->subDays(Vehicle::UNSERVICEABLE_ALERT_DAYS)->startOfDay())
                    ->count(),
            ];

            if ($statusFilter) {
                $query->where('status', $statusFilter);
            }

            if ($hasBroadVisibility) {
                $totalRecords = Vehicle::count();
            } elseif ($role === self::ROLE_UNIT_ADMIN) {
                $totalRecords = Vehicle::where('unit_id', $user->unit_id)->count();
            } else {
                $totalRecords = Vehicle::where('station_id', $user->station_id)->count();
            }

            $filteredRecords = $query->count();
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $vehicles = $query->skip($start)->take($length)->get();

            $data = [];
            foreach ($vehicles as $vehicle) {
                $selectHtml = '<input type="checkbox" class="row-select-checkbox" data-id="'.$vehicle->id.'">';

                $plateHtml = '<span class="plate-badge">' . strtoupper($vehicle->plate_number) . '</span>';

                $eng = $vehicle->engine_number ? ' &middot; Eng: ' . e($vehicle->engine_number) : '';
                // Chassis number gets its own sub-line rather than folding into the
                // first — it's the other identifier records clerks actually look up
                // against OR/CR paperwork, and squeezing it onto one line made that
                // row unreadably long once engine number was already there too.
                $chassisHtml = $vehicle->chassis_number
                    ? '<div class="vehicle-sub-info">Chassis: ' . e($vehicle->chassis_number) . '</div>'
                    : '';
                $specHtml = '<div class="vehicle-main-name">' . e($vehicle->make) . ' ' . e($vehicle->model) . '</div>' .
                            '<div class="vehicle-sub-info">' . (e($vehicle->year_model) ?? 'N/A') . ' &middot; ' . (e($vehicle->color) ?? 'Unspecified') . $eng . '</div>' .
                            $chassisHtml;

                $typeName = $vehicle->type->name ?? 'Unspecified';
                $sourceLabel = Vehicle::SOURCES[$vehicle->source] ?? $vehicle->source;
                $typeHtml = '<span class="badge badge-light px-2 py-1 border" style="font-size:12px; font-weight:600;">' . e($typeName) . '</span>' .
                            '<div class="vehicle-sub-info mt-1"><i class="fas fa-tag mr-1"></i>' . e($sourceLabel) . '</div>';

                if ($vehicle->driver) {
                    $initials = strtoupper(substr($vehicle->driver->firstname, 0, 1) . substr($vehicle->driver->lastname, 0, 1));
                    $middleInitial = $vehicle->driver->middlename ? strtoupper(substr($vehicle->driver->middlename, 0, 1)) : '';
                    $nameParts = [$vehicle->driver->rank, $vehicle->driver->firstname, $middleInitial, $vehicle->driver->lastname, $vehicle->driver->qlfr];
                    $fullName = implode(' ', array_filter($nameParts, fn($value) => !is_null($value) && trim($value) !== ''));

                    $driverHtml = '<div class="driver-chip-wrapper">' .
                                  '<div class="driver-avatar-circle">' . $initials . '</div>' .
                                  '<div class="driver-name-text">' . e($fullName) . '</div>' .
                                  '</div>';
                } else {
                    $driverHtml = '<span class="driver-none">Unassigned</span>';
                }

                $pmsHtml = '—';
                $pmsRowClass = '';
                if ($vehicle->next_pms_date) {
                    $days = now()->startOfDay()->diffInDays($vehicle->next_pms_date->startOfDay(), false);
                    $formattedPmsDate = $vehicle->next_pms_date->format('M d, Y');
                    if ($days < 0) {
                        // Past due — this needs to jump out at a glance, not just read as
                        // another date in the column, so it gets its own alert badge plus
                        // a tinted row (below) rather than only a small inline icon.
                        $overdueDays = abs($days);
                        $pmsHtml = '<div class="pms-date pms-date-overdue">' . $formattedPmsDate . '</div>' .
                                   '<span class="badge-pms pms-badge-overdue"><i class="fas fa-exclamation-triangle"></i> Overdue ' . $overdueDays . 'd</span>' .
                                   $this->pmsPriorityHtml($vehicle);
                        $pmsRowClass = 'row-pms-overdue';
                    } elseif ($days <= 14) {
                        $pmsHtml = '<div class="pms-date">' . $formattedPmsDate . '</div>' .
                                   '<span class="badge-pms pms-badge-soon"><i class="fas fa-clock"></i> Due in ' . $days . 'd</span>' .
                                   $this->pmsPriorityHtml($vehicle);
                    } else {
                        $pmsHtml = '<div class="pms-date">' . $formattedPmsDate . '</div>';
                    }
                }

                $statusClass = strtolower($vehicle->status);
                $statusHtml = '<span class="status-pill status-' . $statusClass . '">' .
                              '<span class="dot"></span>' . $vehicle->status . '</span>';
                if ($vehicle->status === 'BER' && $vehicle->ber_sub_status) {
                    $subLabel = e(Vehicle::BER_SUB_STATUSES[$vehicle->ber_sub_status] ?? $vehicle->ber_sub_status);
                    if ($vehicle->ber_sub_status === 'DISPOSED' && $vehicle->disposal_date) {
                        $subLabel .= ' &middot; ' . e($vehicle->disposal_date->format('M d, Y'));
                    }
                    $statusHtml .= '<div class="vehicle-sub-info mt-1">' . $subLabel . '</div>';
                } elseif ($vehicle->status === 'UNSERVICEABLE' && $vehicle->unserviceable_since) {
                    $days = $vehicle->daysUnserviceable();
                    $statusHtml .= '<div class="vehicle-sub-info mt-1">Since ' . e($vehicle->unserviceable_since->format('M d, Y')) . ' (' . $days . 'd)</div>';
                    if ($vehicle->needsUnserviceableAlert()) {
                        $statusHtml .= '<span class="badge-pms pms-badge-overdue"><i class="fas fa-exclamation-triangle"></i> Needs action</span>';
                    }
                }

                // Vehicles without any registration yet still get a way in — "Add Docs"
                // instead of a dead end — since OR/CR/Insurance upload is no longer forced
                // at creation time only. (Viewers still get a docs button — it's read
                // access, not a write action.) .docs-pill-btn is the premium pill style
                // defined in vehicles/index.blade.php's <style> block.
                $docsHtml = $vehicle->latestRegistration
                    ? '<button type="button" class="docs-pill-btn has-docs btn-history" data-id="'.$vehicle->id.'"><i class="fas fa-folder-open"></i> Docs</button>'
                    : '<button type="button" class="docs-pill-btn no-docs btn-history" data-id="'.$vehicle->id.'"><i class="fas fa-plus"></i> Add Docs</button>';

                // Expiry status now shows right under the Docs button instead of
                // only surfacing on the Dashboard/priority panel — reuses the same
                // Vehicle::registrationDaysRemaining()/REGISTRATION_DUE_SOON_DAYS
                // helpers those panels use, and the existing .badge-pms/.vehicle-sub-info
                // styling the PMS column already established, so "due soon" and
                // "overdue" read the same way in both columns.
                $regRowClass = '';
                if ($vehicle->latestRegistration && $vehicle->latestRegistration->expiry_date) {
                    $regDaysLeft = $vehicle->registrationDaysRemaining();
                    $expiryFormatted = $vehicle->latestRegistration->expiry_date->format('M d, Y');
                    if ($regDaysLeft !== null && $regDaysLeft < 0) {
                        $docsHtml .= '<div class="pms-date pms-date-overdue mt-1">Expired ' . e($expiryFormatted) . '</div>' .
                                     '<span class="badge-pms pms-badge-overdue"><i class="fas fa-exclamation-triangle"></i> ' . abs($regDaysLeft) . 'd overdue</span>';
                        $regRowClass = 'row-reg-overdue';
                    } elseif ($regDaysLeft !== null && $regDaysLeft <= Vehicle::REGISTRATION_DUE_SOON_DAYS) {
                        $docsHtml .= '<div class="pms-date mt-1">Valid until ' . e($expiryFormatted) . '</div>' .
                                     '<span class="badge-pms pms-badge-soon"><i class="fas fa-clock"></i> Due in ' . $regDaysLeft . 'd</span>';
                    } else {
                        $docsHtml .= '<div class="vehicle-sub-info mt-1">Valid until ' . e($expiryFormatted) . '</div>';
                    }
                } elseif (! $vehicle->latestRegistration) {
                    $docsHtml .= '<div class="vehicle-sub-info mt-1"><i class="fas fa-info-circle"></i> No registration on file</div>';
                }

                // "Vehicle Records" — a tabbed modal combining Maintenance & PMS, Repairs,
                // Trip Logs, Fuel Monitoring and Accident Records for this one vehicle
                // (serviceHistory()/tripLogsHistory()/fuelLogsHistory()/accidentsHistory()
                // above) — read-only, so it's offered to every role including Viewer, same
                // reasoning as the QR Code button right next to it. This is the one place
                // an admin can see everything ever logged against a vehicle without
                // leaving Vehicle Inventory to open four separate modules.
                $serviceHistoryBtn = '<button type="button" class="btn btn-sm btn-light border text-info btn-service-history" data-id="'.$vehicle->id.'" title="Vehicle Records"><i class="fas fa-folder-open"></i></button>';

                if ($role === self::ROLE_VIEWER) {
                    $actionsHtml = '
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-light border text-secondary btn-view-qr" data-id="'.$vehicle->id.'" title="QR Code"><i class="fas fa-qrcode"></i></button>
                            '.$serviceHistoryBtn.'
                            <span class="d-inline-flex align-items-center text-muted small ml-2"><i class="fas fa-eye mr-1"></i>View only</span>
                        </div>';
                } else {
                    $actionsHtml = '
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-light border text-secondary btn-view-qr" data-id="'.$vehicle->id.'" title="QR Code"><i class="fas fa-qrcode"></i></button>
                            '.$serviceHistoryBtn.'
                            <button type="button" class="btn btn-sm btn-light border text-primary btn-edit-vehicle" data-id="'.$vehicle->id.'" title="Edit"><i class="fas fa-edit"></i></button>
                            <form method="POST" action="'.route('vehicles.destroy', $vehicle->id).'" class="form-delete-vehicle" style="display:inline-block;">
                                '.csrf_field().'
                                '.method_field('DELETE').'
                                <button type="submit" class="btn btn-sm btn-light border text-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>';
                }

                $data[] = [
                    'select_html' => $selectHtml,
                    'plate_html'  => $plateHtml,
                    'spec_html'   => $specHtml,
                    'type_html'   => $typeHtml,
                    'driver_html' => $driverHtml,
                    'docs_html'   => $docsHtml,
                    'pms_html'    => $pmsHtml,
                    'status_html' => $statusHtml,
                    'actions_html'=> $actionsHtml,
                    // DataTables' built-in "add this class to <tr>" hook — used so an
                    // overdue PMS or an overdue registration is noticeable across the
                    // whole row, not just its own cell. A vehicle can be flagged by
                    // both at once, so both classes can apply together.
                    'DT_RowClass' => trim($pmsRowClass . ' ' . $regRowClass),
                ];
            }

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $data,
                'stats'           => $stats,
                'type_breakdown'  => $typeBreakdown,
            ]);
        }

        $baseQuery = Vehicle::query();
        if ($role === self::ROLE_UNIT_ADMIN) {
            $baseQuery->where('unit_id', $user->unit_id);
        } elseif ($role === self::ROLE_STATION_ADMIN) {
            $baseQuery->where('station_id', $user->station_id);
        }

        $stats = [
            'total'         => (clone $baseQuery)->excludingDisposed()->count(),
            'serviceable'   => (clone $baseQuery)->where('status', 'SERVICEABLE')->count(),
            'unserviceable' => (clone $baseQuery)->where('status', 'UNSERVICEABLE')->count(),
            'ber'           => (clone $baseQuery)->where('status', 'BER')->count(),
        ];

        // Predictive PMS: which vehicles most need attention first, not just which
        // ones happen to be overdue/soon. Bounded to a handful of already-filtered
        // candidates (see PmsPredictionService::topPriorityVehicles), scoped to the
        // same unit/station visibility as everything else on this page.
        $priorityVehicles = app(PmsPredictionService::class)->topPriorityVehicles(clone $baseQuery, 5);

        // Unserviceable 90+ days — same "needs a decision" idea as the priority
        // PMS panel above, but for vehicles stuck Unserviceable rather than due
        // for service (VMIS Additional Updates item 3).
        $unserviceableAlerts = (clone $baseQuery)->unserviceableAlert()
            ->orderBy('unserviceable_since')
            ->take(8)
            ->get();

        // Registration Due — vehicles whose latest OR/CR/Insurance bundle is
        // already expired or expiring within Vehicle::REGISTRATION_DUE_SOON_DAYS.
        // Filtered/sorted in PHP rather than a whereHas() on the latestOfMany
        // relation (registrationDaysRemaining() already does the one date-math
        // calculation that matters, and fleet sizes here are small enough that
        // this costs nothing extra beyond the eager load). Vehicles with no
        // registration on file at all, or whose registration predates expiry_date
        // being tracked, are simply absent from this list — not flagged as due.
        $registrationDueAlerts = (clone $baseQuery)
            ->with('latestRegistration')
            ->get()
            ->filter(fn ($v) => $v->needsRegistrationAlert())
            ->sortBy(fn ($v) => $v->registrationDaysRemaining())
            ->take(8)
            ->values();

        $drivers = Driver::where('status', 'active')->orderBy('lastname')->get();
        $vehicleTypes = VehicleType::withCount('vehicles')->orderBy('name')->get();

        // Units: broad-visibility roles (Super Admin, Admin, Viewer) see every unit.
        // Unit Administrator and Station Administrator only ever see their own unit
        // (a Station Administrator's `unit_id` is the unit their station belongs to).
        $units = $hasBroadVisibility
            ? Unit::orderBy('unit_name')->get()
            : Unit::where('id', $user->unit_id)->orderBy('unit_name')->get();

        // Stations: broad-visibility roles see every station. Unit Administrator sees
        // every station in their unit. Station Administrator sees only their own station.
        if ($hasBroadVisibility) {
            $stations = Station::orderBy('station_name')->get();
        } elseif ($role === self::ROLE_UNIT_ADMIN) {
            $stations = Station::where('unit_id', $user->unit_id)->orderBy('station_name')->get();
        } else {
            $stations = Station::where('id', $user->station_id)->orderBy('station_name')->get();
        }

        $isViewer = ($role === self::ROLE_VIEWER);

        // Controls whether the "auto-fill from OR photo" hint/behavior is offered at
        // all — stays silent rather than showing a button that always fails when no
        // API key has been configured yet.
        $aiDocumentScanningEnabled = app(DocumentIntelligenceService::class)->isConfigured();

        return view('vehicles.index', compact('stats', 'drivers', 'vehicleTypes', 'units', 'stations', 'user', 'hasBroadVisibility', 'isViewer', 'aiDocumentScanningEnabled', 'priorityVehicles', 'unserviceableAlerts', 'registrationDueAlerts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $role = $this->role($user);

        $this->blockViewers($role);

        $rules = [
            'plate_number'       => ['required', 'string', 'max:20', 'unique:vehicles,plate_number'],
            'engine_number'      => ['nullable', 'string', 'max:50', 'unique:vehicles,engine_number'],
            'chassis_number'     => ['nullable', 'string', 'max:50', 'unique:vehicles,chassis_number'],
            'make'               => ['required', 'string', 'max:50'],
            'model'              => ['required', 'string', 'max:50'],
            'vehicle_type_id'    => ['required', 'exists:vehicle_types,id'],
            'station_id'         => ['required', 'exists:stations,id'],
            'year_model'         => ['nullable', 'integer', 'min:1980', 'max:' . (date('Y') + 1)],
            'color'              => ['nullable', 'string', 'max:30'],
            'acquisition_date'   => ['nullable', 'date'],
            'source'             => ['required', 'in:ORGANIC,LOANED,DONATED'],
            'assigned_driver_id' => ['nullable', 'exists:drivers,id'],
            'odometer_km'        => ['nullable', 'integer', 'min:0'],
            'next_pms_date'      => ['nullable', 'date'],
            'status'             => ['required', 'in:SERVICEABLE,UNSERVICEABLE,BER'],
            'ber_sub_status'     => ['nullable', 'required_if:status,BER', 'in:FOR_DISPOSAL,DISPOSED'],
            'disposal_date'      => ['nullable', 'date', 'required_if:ber_sub_status,DISPOSED'],
            'or_file'            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'cr_file'            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'insurance_file'     => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            // When this OR/CR/Insurance bundle stops being valid — drives the
            // "Registration Due" priority panel (see Vehicle::needsRegistrationAlert()).
            'expiry_date'        => ['required', 'date'],
        ];

        if (in_array($role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true)) {
            $rules['unit_id'] = ['required', 'exists:units,id'];
        }

        $validated = $request->validate($rules);
        $validated = $this->applyWriteScope($validated, $user, $role);
        $validated = $this->normalizeBerFields($validated);
        $validated = $this->normalizeUnserviceableTracking($validated);

        $validated['qr_code'] = Str::upper(Str::random(10));
        $validated['is_active'] = '1';
        $validated['encoded_by'] = $user->id;

        $vehicle = Vehicle::create($validated);

        if ($request->hasFile('or_file') && $request->hasFile('cr_file') && $request->hasFile('insurance_file')) {
            $vehicle->registrations()->create([
                'or_file_path'        => $request->file('or_file')->store('vehicle_docs/or', 'public'),
                'cr_file_path'        => $request->file('cr_file')->store('vehicle_docs/cr', 'public'),
                'insurance_file_path' => $request->file('insurance_file')->store('vehicle_docs/insurance', 'public'),
                'registration_year'   => date('Y'),
                'expiry_date'         => $validated['expiry_date'],
                'uploaded_by'         => $user->id,
            ]);
        }

        ActivityLog::record(
            'created',
            'Vehicle',
            'Registered vehicle [' . strtoupper($validated['plate_number']) . '].',
            $vehicle,
            ['after' => Arr::except($validated, ['or_file', 'cr_file', 'insurance_file'])]
        );

        return redirect()->route('vehicles.index')
            ->with('success', 'Vehicle [' . strtoupper($validated['plate_number']) . '] registered successfully!');
    }

    /**
     * Raw field values used to populate the Edit modal via AJAX.
     */
    public function editData(Vehicle $vehicle): JsonResponse
    {
        $this->authorizeVehicleWrite($vehicle);

        return response()->json([
            'id'                 => $vehicle->id,
            'plate_number'       => $vehicle->plate_number,
            'engine_number'      => $vehicle->engine_number,
            'chassis_number'     => $vehicle->chassis_number,
            'make'               => $vehicle->make,
            'model'              => $vehicle->model,
            'vehicle_type_id'    => $vehicle->vehicle_type_id,
            'year_model'         => $vehicle->year_model,
            'color'              => $vehicle->color,
            'source'             => $vehicle->source,
            'ber_sub_status'     => $vehicle->ber_sub_status,
            'disposal_date'      => optional($vehicle->disposal_date)->format('Y-m-d'),
            'unit_id'            => $vehicle->unit_id,
            'station_id'         => $vehicle->station_id,
            'assigned_driver_id' => $vehicle->assigned_driver_id,
            'odometer_km'        => $vehicle->odometer_km,
            'next_pms_date'      => optional($vehicle->next_pms_date)->format('Y-m-d'),
            'status'             => $vehicle->status,
            // Read-only in the Edit modal — VehicleController sets/clears this itself
            // whenever status changes, so the form only ever displays it as context.
            'unserviceable_since'  => optional($vehicle->unserviceable_since)->format('M d, Y'),
            'days_unserviceable'   => $vehicle->daysUnserviceable(),
        ]);
    }

    /**
     * Update an existing vehicle's core details. OR/CR documents are handled
     * separately via storeRegistration() — editing here never touches them.
     */
    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeVehicleWrite($vehicle);

        $user = auth()->user();
        $role = $this->role($user);

        $rules = [
            'plate_number'       => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate_number')->ignore($vehicle->id)],
            'engine_number'      => ['nullable', 'string', 'max:50', Rule::unique('vehicles', 'engine_number')->ignore($vehicle->id)],
            'chassis_number'     => ['nullable', 'string', 'max:50', Rule::unique('vehicles', 'chassis_number')->ignore($vehicle->id)],
            'make'               => ['required', 'string', 'max:50'],
            'model'              => ['required', 'string', 'max:50'],
            'vehicle_type_id'    => ['required', 'exists:vehicle_types,id'],
            'station_id'         => ['required', 'exists:stations,id'],
            'year_model'         => ['nullable', 'integer', 'min:1980', 'max:' . (date('Y') + 1)],
            'color'              => ['nullable', 'string', 'max:30'],
            'source'             => ['required', 'in:ORGANIC,LOANED,DONATED'],
            'assigned_driver_id' => ['nullable', 'exists:drivers,id'],
            'odometer_km'        => ['nullable', 'integer', 'min:0'],
            'next_pms_date'      => ['nullable', 'date'],
            'status'             => ['required', 'in:SERVICEABLE,UNSERVICEABLE,BER'],
            'ber_sub_status'     => ['nullable', 'required_if:status,BER', 'in:FOR_DISPOSAL,DISPOSED'],
            'disposal_date'      => ['nullable', 'date', 'required_if:ber_sub_status,DISPOSED'],
        ];

        if (in_array($role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true)) {
            $rules['unit_id'] = ['required', 'exists:units,id'];
        }

        $validated = $request->validate($rules);
        $validated = $this->applyWriteScope($validated, $user, $role, $vehicle);
        $validated = $this->normalizeBerFields($validated);
        // Must run before $vehicle->update() below — it compares the incoming
        // status against $vehicle's still-original (pre-update) status.
        $validated = $this->normalizeUnserviceableTracking($validated, $vehicle);

        $before = $vehicle->getOriginal();
        $vehicle->update($validated);
        $changed = $vehicle->getChanges();
        unset($changed['updated_at']);

        if (!empty($changed)) {
            ActivityLog::record(
                'updated',
                'Vehicle',
                'Updated vehicle [' . strtoupper($vehicle->plate_number) . '].',
                $vehicle,
                ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicle [' . strtoupper($vehicle->plate_number) . '] updated successfully!',
        ]);
    }

    /**
     * Upload a new year's OR/CR/Insurance for an existing vehicle (annual
     * re-registration) without touching any of the vehicle's other details.
     * All three documents are required every year — an OR or CR alone isn't
     * proof the vehicle is currently insured, so the same yearly-renewal
     * discipline applies to all three rather than making Insurance optional.
     */
    public function storeRegistration(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeVehicleWrite($vehicle);

        $user = auth()->user();

        $validated = $request->validate([
            'registration_year' => [
                'required', 'integer', 'digits:4', 'min:1980', 'max:' . (date('Y') + 1),
                Rule::unique('vehicle_registrations', 'registration_year')
                    ->where(fn ($q) => $q->where('vehicle_id', $vehicle->id)),
            ],
            'or_file'        => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'cr_file'        => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'insurance_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            // When this OR/CR/Insurance bundle stops being valid — drives the
            // "Registration Due" priority panel (see Vehicle::needsRegistrationAlert()).
            'expiry_date'    => ['required', 'date'],
        ], [
            'registration_year.unique' => 'A registration for this year has already been uploaded for this vehicle.',
        ]);

        $vehicle->registrations()->create([
            'or_file_path'        => $request->file('or_file')->store('vehicle_docs/or', 'public'),
            'cr_file_path'        => $request->file('cr_file')->store('vehicle_docs/cr', 'public'),
            'insurance_file_path' => $request->file('insurance_file')->store('vehicle_docs/insurance', 'public'),
            'registration_year'   => $validated['registration_year'],
            'expiry_date'         => $validated['expiry_date'],
            'uploaded_by'         => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'New ' . $validated['registration_year'] . ' registration uploaded for [' . strtoupper($vehicle->plate_number) . '].',
        ]);
    }

    public function getHistory($id): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $vehicle = Vehicle::with(['registrations.uploader'])->findOrFail($id);

        if (! $this->canView($vehicle, $user, $role)) {
            return response()->json(['error' => 'Unauthorized Access'], 403);
        }

        return response()->json([
            'plate_number' => $vehicle->plate_number,
            'make_model' => $vehicle->make . ' ' . $vehicle->model,
            'registrations' => $vehicle->registrations->map(function($reg) {
                return [
                    'year' => $reg->registration_year,
                    // Null-safe: insurance_file_path didn't exist on older rows, and
                    // in principle or/cr could be blank on a very old/legacy record —
                    // the modal shows a "Not uploaded" state for any of the three
                    // rather than linking to a document that was never stored.
                    'or_url'        => $reg->or_file_path ? route('vehicles.document', ['path' => $reg->or_file_path]) : null,
                    'cr_url'        => $reg->cr_file_path ? route('vehicles.document', ['path' => $reg->cr_file_path]) : null,
                    'insurance_url' => $reg->insurance_file_path ? route('vehicles.document', ['path' => $reg->insurance_file_path]) : null,
                    'uploader' => $reg->uploader->fullname ?? 'Unknown User',
                    'date' => $reg->created_at->format('M d, Y h:i A'),
                    // expiry_date is null on older rows uploaded before this was
                    // tracked — the modal just omits the "Valid until" line then,
                    // same null-safe treatment as the document links above.
                    'expiry_date'   => optional($reg->expiry_date)->format('M d, Y'),
                    'expiry_status' => $this->expiryStatus($reg->expiry_date),
                ];
            })
        ]);
    }

    /**
     * 'overdue' | 'soon' (within Vehicle::REGISTRATION_DUE_SOON_DAYS) | 'ok' | null
     * (no expiry on file) for one registration row's expiry_date — same
     * timestamp-diff approach as Vehicle::registrationDaysRemaining(), just
     * for an arbitrary date rather than only the vehicle's latest registration,
     * since the Docs modal shows every year's status, not just the current one.
     */
    private function expiryStatus(?\Carbon\Carbon $expiry): ?string
    {
        if (! $expiry) {
            return null;
        }

        $today = now()->startOfDay();
        $expiryDay = $expiry->copy()->startOfDay();
        $days = (int) round(($expiryDay->getTimestamp() - $today->getTimestamp()) / 86400);

        if ($days < 0) {
            return 'overdue';
        }

        return $days <= Vehicle::REGISTRATION_DUE_SOON_DAYS ? 'soon' : 'ok';
    }

    /**
     * Every Maintenance & PMS and Repair job ever logged for one vehicle, newest
     * first, in a single combined list — the thing getHistory() above deliberately
     * isn't (that one's scoped to OR/CR registration documents only). Completed
     * jobs are never deleted from the Maintenance/Repair pages (see
     * MaintenanceController/RepairController::index, which list every stage, not
     * just Completed), so this is simply every maintenance_records row for this
     * vehicle_id regardless of maintenance_type or stage — a user no longer has to
     * jump to the separate Maintenance & PMS / Repairs pages and filter by vehicle
     * to see everything done to one unit. Read-only, so Viewer accounts get it too,
     * same as getHistory().
     *
     * Server-side paginated (start/length) with an optional search and module
     * filter, same reasoning as MaintenanceController/RepairController::index's
     * own AJAX branch: a vehicle in long-term service can accumulate hundreds of
     * records, and a modal that dumped every one of them into the DOM at once
     * would be slow to render and effectively unbrowsable — no way to jump to a
     * specific job without endless scrolling. The three summary counts (Total/
     * Maintenance/Repairs) deliberately come from a SEPARATE unfiltered query
     * below, so narrowing the list with a search or module filter never makes
     * those headline numbers look wrong.
     */
    public function serviceHistory(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $vehicle = Vehicle::findOrFail($id);

        if (! $this->canView($vehicle, $user, $role)) {
            return response()->json(['error' => 'Unauthorized Access'], 403);
        }

        $allForVehicle = MaintenanceRecord::where('vehicle_id', $vehicle->id)->get(['maintenance_type', 'stage', 'cost']);
        $summary = [
            'total'       => $allForVehicle->count(),
            'maintenance' => $allForVehicle->where('maintenance_type', '!=', 'REPAIR')->count(),
            'repairs'     => $allForVehicle->where('maintenance_type', '==', 'REPAIR')->count(),
            // Only Completed jobs represent real spend — an in-flight request's
            // cost field is still empty, same completedOnly() reasoning
            // MaintenanceController/RepairController use for their own stats.
            'total_cost'  => (float) $allForVehicle->where('stage', MaintenanceRecord::STAGE_COMPLETED)->sum('cost'),
        ];

        $query = MaintenanceRecord::with('recorder')->where('vehicle_id', $vehicle->id);

        if ($module = $request->get('module')) {
            if ($module === 'REPAIR') {
                $query->where('maintenance_type', 'REPAIR');
            } elseif ($module === 'MAINTENANCE') {
                $query->where('maintenance_type', '!=', 'REPAIR');
            }
        }

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('control_number', 'like', "%{$search}%")
                  ->orWhere('performed_by', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start  = max(0, (int) $request->get('start', 0));
        $length = min(50, max(1, (int) $request->get('length', 10)));

        $records = $query->orderByDesc('service_date')->orderByDesc('id')->skip($start)->take($length)->get();

        return response()->json([
            'plate_number'    => $vehicle->plate_number,
            'make_model'      => trim($vehicle->make . ' ' . $vehicle->model),
            'summary'         => $summary,
            'recordsTotal'    => $summary['total'],
            'recordsFiltered' => $recordsFiltered,
            'start'           => $start,
            'length'          => $length,
            'records'         => $records->map(function ($record) {
                return [
                    'id'                  => $record->id,
                    'module'              => $record->maintenance_type === 'REPAIR' ? 'Repair' : 'Maintenance & PMS',
                    'type_label'          => MaintenanceRecord::TYPES[$record->maintenance_type] ?? $record->maintenance_type,
                    'type_color'          => MaintenanceRecord::TYPE_COLORS[$record->maintenance_type] ?? 'light',
                    'control_number'      => $record->control_number,
                    'stage'               => $record->stage,
                    'stage_label'         => MaintenanceRecord::STAGES[$record->stage] ?? $record->stage,
                    'stage_color'         => MaintenanceRecord::STAGE_COLORS[$record->stage] ?? 'secondary',
                    'description'         => $record->description,
                    // Before Completed, service_date is just a request_date placeholder
                    // (see MaintenanceRecord/the stage-workflow migration) — flagged here
                    // so the timeline can label it "(requested)" same as the two list pages.
                    'service_date'        => optional($record->service_date)->format('M d, Y'),
                    'is_placeholder_date' => $record->stage !== MaintenanceRecord::STAGE_COMPLETED,
                    'odometer_km'         => $record->odometer_km,
                    'cost'                => $record->cost !== null ? (float) $record->cost : null,
                    'performed_by'        => $record->performed_by,
                    'next_due_date'       => optional($record->next_due_date)->format('M d, Y'),
                    'logged_by'           => optional($record->recorder)->fullname ?? 'System',
                    'logged_at'           => $record->created_at->format('M d, Y'),
                ];
            })->values(),
        ]);
    }

    /**
     * Trip Logs for this vehicle, for the "Vehicle Records" modal on Vehicle
     * Inventory — the same read-only, server-side-paginated pattern as
     * serviceHistory() above, just pointed at a different table, so an admin
     * never has to leave this page to see a vehicle's full usage/driving
     * history, not just its maintenance jobs.
     */
    public function tripLogsHistory(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $vehicle = Vehicle::findOrFail($id);

        if (! $this->canView($vehicle, $user, $role)) {
            return response()->json(['error' => 'Unauthorized Access'], 403);
        }

        $allForVehicle = TripLog::where('vehicle_id', $vehicle->id)->get(['round_trip_group', 'odometer_start', 'odometer_end', 'trip_date']);
        $summary = [
            'total'       => $allForVehicle->count(),
            // Each round trip is two linked rows sharing a round_trip_group — counted
            // once per distinct group, not once per leg, so this reads as "round trips
            // taken", not "round-trip legs logged".
            'round_trips' => $allForVehicle->whereNotNull('round_trip_group')->pluck('round_trip_group')->unique()->count(),
            'total_distance_km' => (int) $allForVehicle->filter(fn ($t) => $t->odometer_start !== null && $t->odometer_end !== null && $t->odometer_end >= $t->odometer_start)
                ->sum(fn ($t) => $t->odometer_end - $t->odometer_start),
            'last_trip_date' => $allForVehicle->isNotEmpty() ? optional($allForVehicle->sortByDesc('trip_date')->first()->trip_date)->format('M d, Y') : null,
        ];

        $query = TripLog::with('driver')->where('vehicle_id', $vehicle->id);

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('origin', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%")
                  ->orWhere('passengers', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start  = max(0, (int) $request->get('start', 0));
        $length = min(50, max(1, (int) $request->get('length', 10)));

        $trips = $query->orderByDesc('trip_date')->orderByDesc('id')->skip($start)->take($length)->get();

        return response()->json([
            'plate_number'    => $vehicle->plate_number,
            'summary'         => $summary,
            'recordsTotal'    => $summary['total'],
            'recordsFiltered' => $recordsFiltered,
            'start'           => $start,
            'length'          => $length,
            'records'         => $trips->map(function (TripLog $trip) {
                $distance = ($trip->odometer_start !== null && $trip->odometer_end !== null && $trip->odometer_end >= $trip->odometer_start)
                    ? $trip->odometer_end - $trip->odometer_start
                    : null;

                return [
                    'id'              => $trip->id,
                    'trip_date'       => $trip->trip_date->format('M d, Y'),
                    'departure_time'  => $trip->departure_time ? date('h:i A', strtotime($trip->departure_time)) : null,
                    'arrival_time'    => $trip->arrival_time ? date('h:i A', strtotime($trip->arrival_time)) : null,
                    'origin'          => $trip->origin,
                    'destination'     => $trip->destination,
                    'is_round_trip'   => $trip->isRoundTrip(),
                    'leg'             => $trip->leg,
                    'purpose'         => $trip->purpose,
                    'odometer_start'  => $trip->odometer_start,
                    'odometer_end'    => $trip->odometer_end,
                    'distance_km'     => $distance,
                    'passengers'      => $trip->passengers,
                    'remarks'         => $trip->remarks,
                    'driver_name'     => $trip->driver ? trim($trip->driver->firstname . ' ' . $trip->driver->lastname) : null,
                    'logged_at'       => $trip->created_at->format('M d, Y'),
                ];
            })->values(),
        ]);
    }

    /**
     * Fuel Logs for this vehicle, for the "Vehicle Records" modal — mirrors
     * tripLogsHistory() above; efficiency figures reuse FuelLog's own
     * kmPerLiter()/distanceSinceLastRefuel() helpers so this modal can never
     * disagree with the Fuel Monitoring module's own numbers.
     */
    public function fuelLogsHistory(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $vehicle = Vehicle::findOrFail($id);

        if (! $this->canView($vehicle, $user, $role)) {
            return response()->json(['error' => 'Unauthorized Access'], 403);
        }

        $allForVehicle = FuelLog::where('vehicle_id', $vehicle->id)->get();
        $kmlValues = $allForVehicle->map(fn ($log) => $log->kmPerLiter())->filter(fn ($v) => $v !== null);

        $summary = [
            'total'        => $allForVehicle->count(),
            'total_liters' => (float) $allForVehicle->sum('liters'),
            'total_cost'   => (float) $allForVehicle->sum('total_cost'),
            'avg_kml'      => $kmlValues->isNotEmpty() ? round($kmlValues->avg(), 2) : null,
        ];

        $query = FuelLog::with('driver')->where('vehicle_id', $vehicle->id);

        $recordsFiltered = (clone $query)->count();

        $start  = max(0, (int) $request->get('start', 0));
        $length = min(50, max(1, (int) $request->get('length', 10)));

        $logs = $query->orderByDesc('refuel_date')->orderByDesc('id')->skip($start)->take($length)->get();

        return response()->json([
            'plate_number'    => $vehicle->plate_number,
            'summary'         => $summary,
            'recordsTotal'    => $summary['total'],
            'recordsFiltered' => $recordsFiltered,
            'start'           => $start,
            'length'          => $length,
            'records'         => $logs->map(function (FuelLog $log) {
                return [
                    'id'                => $log->id,
                    'refuel_date'       => $log->refuel_date->format('M d, Y'),
                    'liters'            => (float) $log->liters,
                    'total_cost'        => (float) $log->total_cost,
                    'price_per_liter'   => $log->pricePerLiter(),
                    'odometer_reading'  => $log->odometer_reading,
                    'distance_km'       => $log->distanceSinceLastRefuel(),
                    'km_per_liter'      => $log->kmPerLiter(),
                    'receipt_url'       => $log->receipt_path ? route('fuel-logs.document', ['path' => $log->receipt_path]) : null,
                    'driver_name'       => $log->driver ? trim($log->driver->firstname . ' ' . $log->driver->lastname) : null,
                    'logged_at'         => $log->created_at->format('M d, Y'),
                ];
            })->values(),
        ]);
    }

    /**
     * Accident Records for this vehicle, for the "Vehicle Records" modal —
     * mirrors tripLogsHistory()/fuelLogsHistory() above.
     */
    public function accidentsHistory(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        $role = $this->role($user);
        $vehicle = Vehicle::findOrFail($id);

        if (! $this->canView($vehicle, $user, $role)) {
            return response()->json(['error' => 'Unauthorized Access'], 403);
        }

        $allForVehicle = VehicleAccident::where('vehicle_id', $vehicle->id)->get(['severity', 'estimated_cost']);
        $summary = [
            'total'               => $allForVehicle->count(),
            'minor'               => $allForVehicle->where('severity', VehicleAccident::SEVERITY_MINOR)->count(),
            'moderate'            => $allForVehicle->where('severity', VehicleAccident::SEVERITY_MODERATE)->count(),
            'major'               => $allForVehicle->where('severity', VehicleAccident::SEVERITY_MAJOR)->count(),
            'total_estimated_cost'=> (float) $allForVehicle->sum('estimated_cost'),
        ];

        $query = VehicleAccident::with('driver')->where('vehicle_id', $vehicle->id);

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('location', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('police_report_no', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start  = max(0, (int) $request->get('start', 0));
        $length = min(50, max(1, (int) $request->get('length', 10)));

        $accidents = $query->orderByDesc('accident_date')->orderByDesc('id')->skip($start)->take($length)->get();

        return response()->json([
            'plate_number'    => $vehicle->plate_number,
            'summary'         => $summary,
            'recordsTotal'    => $summary['total'],
            'recordsFiltered' => $recordsFiltered,
            'start'           => $start,
            'length'          => $length,
            'records'         => $accidents->map(function (VehicleAccident $accident) {
                return [
                    'id'                => $accident->id,
                    'accident_date'     => $accident->accident_date->format('M d, Y'),
                    'accident_time'     => $accident->accident_time ? date('h:i A', strtotime($accident->accident_time)) : null,
                    'location'          => $accident->location,
                    'description'       => $accident->description,
                    'severity'          => $accident->severity,
                    'severity_label'    => VehicleAccident::SEVERITIES[$accident->severity] ?? $accident->severity,
                    'estimated_cost'    => $accident->estimated_cost !== null ? (float) $accident->estimated_cost : null,
                    'police_report_no'  => $accident->police_report_no,
                    'photo_url'         => $accident->photo_path ? route('accidents.document', ['path' => $accident->photo_path]) : null,
                    'driver_name'       => $accident->driver ? trim($accident->driver->firstname . ' ' . $accident->driver->lastname) : null,
                    'logged_at'         => $accident->created_at->format('M d, Y'),
                ];
            })->values(),
        ]);
    }

    public function destroy($id): RedirectResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        $this->authorizeVehicleWrite($vehicle);

        $snapshot = $vehicle->toArray();
        $vehicle->delete();

        ActivityLog::record(
            'deleted',
            'Vehicle',
            'Deleted vehicle [' . strtoupper($vehicle->plate_number) . '].',
            $vehicle,
            ['before' => $snapshot]
        );

        return redirect()->route('vehicles.index')->with('success', 'Vehicle record deleted.');
    }

    /**
     * Serve an uploaded OR/CR document directly from the private storage disk,
     * bypassing the public/storage symlink entirely (unreliable on Windows/WAMP)
     * and keeping document access behind the same auth middleware as everything else.
     */
    public function viewDocument(string $path)
    {
        // Only ever serve files that live under vehicle_docs/ — never an arbitrary path.
        if (! str_starts_with($path, 'vehicle_docs/') || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    /**
     * Live duplicate check for plate/engine/chassis. Pass exclude_id when
     * checking from the Edit modal so a vehicle doesn't flag itself.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $field = $request->input('field'); 
        $value = strtoupper(trim($request->input('value')));
        $excludeId = $request->input('exclude_id');

        if (!in_array($field, ['plate_number', 'engine_number', 'chassis_number']) || empty($value)) {
            return response()->json(['exists' => false]);
        }

        $query = Vehicle::where($field, $value);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $existingRecord = $query->first();

        if ($existingRecord) {
            return response()->json([
                'exists'       => true,
                'field'        => $field,
                'value'        => $value,
                'plate_number' => $existingRecord->plate_number,
                'make_model'   => $existingRecord->make . ' ' . $existingRecord->model,
            ]);
        }

        return response()->json(['exists' => false]);
    }

    /**
     * Streams the vehicle's QR sticker as SVG, with the PRO5 seal embedded in the
     * center. SVG deliberately: it needs no PHP image extension at all (unlike the
     * PNG/Imagick backend, which isn't available on stock WAMP), and stays crisp at
     * any print size since it's vector, not a fixed-resolution raster. High error
     * correction is what makes it safe to cover part of the code with a logo —
     * without it, a logo this size would make the code unreliable to scan.
     */
    public function qrImage(Vehicle $vehicle)
    {
        $this->authorizeVehicleView($vehicle);

        $url = route('vehicles.scan', $vehicle->qr_code);

        $svg = QrCode::format('svg')
            ->size(400)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($url);

        $svg = $this->embedLogoInQrSvg($svg, public_path('images/pro5-logo.png'));

        return response($svg)->header('Content-Type', 'image/svg+xml');
    }

    /**
     * Injects a centered logo (with a white backing plate for contrast/legibility)
     * directly into a generated QR SVG string. Sized and positioned proportionally
     * to the SVG's own viewBox, so it holds up correctly regardless of the size
     * the QR was generated at.
     */
    protected function embedLogoInQrSvg(string $svg, string $logoPath): string
    {
        if (! is_file($logoPath)) {
            return $svg;
        }

        preg_match('/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $svg, $matches);
        $vbWidth  = isset($matches[1]) ? (float) $matches[1] : 400.0;
        $vbHeight = isset($matches[2]) ? (float) $matches[2] : 400.0;

        // ~22% of the code's width is the widely-used safe ceiling for a center logo
        // when the code is generated with High error correction.
        $logoSize    = $vbWidth * 0.22;
        $logoX       = ($vbWidth - $logoSize) / 2;
        $logoY       = ($vbHeight - $logoSize) / 2;
        $padding     = $logoSize * 0.14;
        $backingSize = $logoSize + ($padding * 2);
        $backingX    = $logoX - $padding;
        $backingY    = $logoY - $padding;

        $mime      = mime_content_type($logoPath) ?: 'image/png';
        $logoData  = base64_encode((string) file_get_contents($logoPath));

        $overlay = sprintf(
            '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="#ffffff" stroke="#e2e8f0" stroke-width="0.6"/>'
            . '<image x="%.2f" y="%.2f" width="%.2f" height="%.2f" href="data:%s;base64,%s" preserveAspectRatio="xMidYMid meet"/>',
            $backingX, $backingY, $backingSize, $backingSize, $backingSize * 0.18,
            $logoX, $logoY, $logoSize, $logoSize, $mime, $logoData
        );

        return str_replace('</svg>', $overlay . '</svg>', $svg);
    }

    /**
     * Vehicle summary + generation/print provenance for the individual QR modal.
     */
    public function qrData(Vehicle $vehicle): JsonResponse
    {
        $this->authorizeVehicleView($vehicle);

        $vehicle->load(['encoder', 'type', 'latestQrPrint.printer']);

        return response()->json([
            'plate_number'     => $vehicle->plate_number,
            'make_model'       => $vehicle->make . ' ' . $vehicle->model,
            'type_name'        => optional($vehicle->type)->name ?? 'Unspecified',
            'status'           => $vehicle->status,
            'qr_image_url'     => route('vehicles.qr-image', $vehicle),
            'scan_url'         => route('vehicles.scan', $vehicle->qr_code),
            'generated_by'     => optional($vehicle->encoder)->fullname ?? 'Unknown User',
            'generated_at'     => $vehicle->created_at->format('M d, Y h:i A'),
            'last_printed_by'  => optional(optional($vehicle->latestQrPrint)->printer)->fullname,
            'last_printed_at'  => optional($vehicle->latestQrPrint)?->printed_at?->format('M d, Y h:i A'),
        ]);
    }

    /**
     * Print layout for one or many vehicles' QR stickers, sized for real-world
     * printing. Every vehicle included gets a VehicleQrPrint audit row logged
     * against the current user before the page renders — printing IS the action
     * being audited, so it's logged here rather than at image-view time.
     */
    public function printQr(Request $request)
    {
        $ids = array_values(array_filter(explode(',', (string) $request->get('ids'))));
        abort_if(empty($ids), 404, 'No vehicles selected.');

        $user = auth()->user();
        $role = $this->role($user);

        $vehicles = Vehicle::with('type')->whereIn('id', $ids)->get()
            ->filter(fn ($v) => $this->canView($v, $user, $role))
            ->values();

        abort_if($vehicles->isEmpty(), 404, 'No accessible vehicles in that selection.');

        $context = $vehicles->count() > 1 ? 'bulk' : 'single';

        foreach ($vehicles as $vehicle) {
            VehicleQrPrint::create([
                'vehicle_id' => $vehicle->id,
                'printed_by' => $user->id,
                'context'    => $context,
                'printed_at' => now(),
            ]);
        }

        return view('vehicles.qr-print', compact('vehicles'));
    }

    /**
     * Overrides unit_id/station_id on a validated payload to match what the
     * current role is actually allowed to write, regardless of what was submitted.
     * This is the server-side source of truth — the UI only hides/limits choices
     * as a convenience, this is what actually enforces it.
     */
    protected function applyWriteScope(array $validated, $user, string $role, ?Vehicle $vehicle = null): array
    {
        if ($role === self::ROLE_UNIT_ADMIN) {
            // Can pick any station within their unit, but the unit itself is always their own.
            $validated['unit_id'] = $user->unit_id;
        } elseif ($role === self::ROLE_STATION_ADMIN) {
            // Locked to their own unit AND their own station — nothing user-selectable here.
            $validated['unit_id'] = $user->unit_id;
            $validated['station_id'] = $user->station_id;
        }
        // Super Admin / Admin: unit_id comes from the validated form input as-is.

        return $validated;
    }

    /**
     * Server-side source of truth for ber_sub_status/disposal_date — the form
     * only shows/hides these fields as a convenience; this is what actually
     * guarantees a non-BER vehicle can never end up with a leftover sub-status
     * or disposal date, and a BER-but-not-Disposed vehicle can never end up
     * with a leftover disposal date, regardless of what the client submitted.
     */
    protected function normalizeBerFields(array $validated): array
    {
        if (($validated['status'] ?? null) !== 'BER') {
            $validated['ber_sub_status'] = null;
            $validated['disposal_date'] = null;
        } elseif (($validated['ber_sub_status'] ?? null) !== 'DISPOSED') {
            $validated['disposal_date'] = null;
        }

        return $validated;
    }

    /**
     * Server-side source of truth for unserviceable_since (VMIS Additional
     * Updates item 3) — the form never submits this field at all; it's set
     * or cleared here purely from the incoming status, so it can't be
     * tampered with client-side.
     *
     * - Status isn't UNSERVICEABLE: always null.
     * - Status is UNSERVICEABLE and the vehicle already was (unchanged): keep
     *   the existing date, so saving other edits doesn't reset the 90-day clock.
     * - Status is newly UNSERVICEABLE (a new vehicle, or one just switched
     *   into this status): stamped with today.
     */
    protected function normalizeUnserviceableTracking(array $validated, ?Vehicle $vehicle = null): array
    {
        if (($validated['status'] ?? null) !== 'UNSERVICEABLE') {
            $validated['unserviceable_since'] = null;
            return $validated;
        }

        if ($vehicle && $vehicle->status === 'UNSERVICEABLE' && $vehicle->unserviceable_since) {
            $validated['unserviceable_since'] = $vehicle->unserviceable_since;
        } else {
            $validated['unserviceable_since'] = now();
        }

        return $validated;
    }

    /**
     * Whether the given user/role can even *see* this vehicle (used for read paths like history).
     */
    protected function canView(Vehicle $vehicle, $user, string $role): bool
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

    /**
     * 403s unless the current user can see this vehicle at all — used by the QR
     * image/data endpoints, which are read-only (even Viewers can view/print QR
     * codes; only the write paths block them).
     */
    protected function authorizeVehicleView(Vehicle $vehicle): void
    {
        $user = auth()->user();
        $role = $this->role($user);

        if (! $this->canView($vehicle, $user, $role)) {
            abort(403, 'Unauthorized Action');
        }
    }

    /**
     * 403s unless the current user is both allowed to write at all (not a Viewer)
     * and allowed to write to this specific vehicle (within their unit/station).
     */
    protected function authorizeVehicleWrite(Vehicle $vehicle): void
    {
        $user = auth()->user();
        $role = $this->role($user);

        $this->blockViewers($role);

        if ($role === self::ROLE_UNIT_ADMIN && $vehicle->unit_id != $user->unit_id) {
            abort(403, 'Unauthorized Action');
        }
        if ($role === self::ROLE_STATION_ADMIN && $vehicle->station_id != $user->station_id) {
            abort(403, 'Unauthorized Action');
        }
    }

    /**
     * Viewer accounts are read-only system-wide — block every write path.
     */
    protected function blockViewers(string $role): void
    {
        if ($role === self::ROLE_VIEWER) {
            abort(403, 'Viewer accounts have read-only access.');
        }
    }
}
