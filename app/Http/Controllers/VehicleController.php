<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\VehicleType;
use App\Models\VehicleQrPrint;
use App\Models\Unit;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
     * Display vehicle inventory with dynamic filters & summary metrics.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->role($user);
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

            // Live stat cards: reflect unit/station/search/type filters, but deliberately
            // NOT the status filter itself — otherwise picking "Serviceable" would collapse
            // the other three cards to near-zero, which is accurate but not useful. This way
            // the cards always show the real breakdown for whatever scope is currently active.
            $statsQuery = (clone $query)->toBase();
            $stats = [
                'total'         => (clone $statsQuery)->count(),
                'serviceable'   => (clone $statsQuery)->where('status', 'SERVICEABLE')->count(),
                'unserviceable' => (clone $statsQuery)->where('status', 'UNSERVICEABLE')->count(),
                'ber'           => (clone $statsQuery)->where('status', 'BER')->count(),
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
                $specHtml = '<div class="vehicle-main-name">' . e($vehicle->make) . ' ' . e($vehicle->model) . '</div>' .
                            '<div class="vehicle-sub-info">' . (e($vehicle->year_model) ?? 'N/A') . ' &middot; ' . (e($vehicle->color) ?? 'Unspecified') . $eng . '</div>';

                $typeName = $vehicle->type->name ?? 'Unspecified';
                $typeHtml = '<span class="badge badge-light px-2 py-1 border" style="font-size:12px; font-weight:600;">' . e($typeName) . '</span>';

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
                                   '<span class="badge-pms pms-badge-overdue"><i class="fas fa-exclamation-triangle"></i> Overdue ' . $overdueDays . 'd</span>';
                        $pmsRowClass = 'row-pms-overdue';
                    } elseif ($days <= 14) {
                        $pmsHtml = '<div class="pms-date">' . $formattedPmsDate . '</div>' .
                                   '<span class="badge-pms pms-badge-soon"><i class="fas fa-clock"></i> Due in ' . $days . 'd</span>';
                    } else {
                        $pmsHtml = '<div class="pms-date">' . $formattedPmsDate . '</div>';
                    }
                }

                $statusClass = strtolower($vehicle->status);
                $statusHtml = '<span class="status-pill status-' . $statusClass . '">' .
                              '<span class="dot"></span>' . $vehicle->status . '</span>';

                // Vehicles without any registration yet still get a way in — "Add Docs"
                // instead of a dead end — since OR/CR upload is no longer forced at creation time only.
                // (Viewers still get a docs button — it's read access, not a write action.)
                $docsHtml = $vehicle->latestRegistration
                    ? '<button type="button" class="btn btn-sm btn-outline-info btn-history py-1 px-2" style="font-size:12px;" data-id="'.$vehicle->id.'"><i class="fas fa-folder-open"></i> Docs</button>'
                    : '<button type="button" class="btn btn-sm btn-outline-secondary btn-history py-1 px-2" style="font-size:12px;" data-id="'.$vehicle->id.'"><i class="fas fa-plus"></i> Add Docs</button>';

                if ($role === self::ROLE_VIEWER) {
                    $actionsHtml = '
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-light border text-secondary btn-view-qr" data-id="'.$vehicle->id.'" title="QR Code"><i class="fas fa-qrcode"></i></button>
                            <span class="d-inline-flex align-items-center text-muted small ml-2"><i class="fas fa-eye mr-1"></i>View only</span>
                        </div>';
                } else {
                    $actionsHtml = '
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-light border text-secondary btn-view-qr" data-id="'.$vehicle->id.'" title="QR Code"><i class="fas fa-qrcode"></i></button>
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
                    // overdue PMS is noticeable across the whole row, not just its cell.
                    'DT_RowClass' => $pmsRowClass,
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
            'total'         => (clone $baseQuery)->count(),
            'serviceable'   => (clone $baseQuery)->where('status', 'SERVICEABLE')->count(),
            'unserviceable' => (clone $baseQuery)->where('status', 'UNSERVICEABLE')->count(),
            'ber'           => (clone $baseQuery)->where('status', 'BER')->count(),
        ];

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

        return view('vehicles.index', compact('stats', 'drivers', 'vehicleTypes', 'units', 'stations', 'user', 'hasBroadVisibility', 'isViewer'));
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
            'assigned_driver_id' => ['nullable', 'exists:drivers,id'],
            'odometer_km'        => ['nullable', 'integer', 'min:0'],
            'next_pms_date'      => ['nullable', 'date'],
            'status'             => ['required', 'in:SERVICEABLE,UNSERVICEABLE,BER'],
            'or_file'            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'cr_file'            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ];

        if (in_array($role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true)) {
            $rules['unit_id'] = ['required', 'exists:units,id'];
        }

        $validated = $request->validate($rules);
        $validated = $this->applyWriteScope($validated, $user, $role);

        $validated['qr_code'] = Str::upper(Str::random(10));
        $validated['is_active'] = '1';
        $validated['encoded_by'] = $user->id;

        $vehicle = Vehicle::create($validated);

        if ($request->hasFile('or_file') && $request->hasFile('cr_file')) {
            $vehicle->registrations()->create([
                'or_file_path' => $request->file('or_file')->store('vehicle_docs/or', 'public'),
                'cr_file_path' => $request->file('cr_file')->store('vehicle_docs/cr', 'public'),
                'registration_year' => date('Y'),
                'uploaded_by' => $user->id,
            ]);
        }

        ActivityLog::record(
            'created',
            'Vehicle',
            'Registered vehicle [' . strtoupper($validated['plate_number']) . '].',
            $vehicle,
            ['after' => Arr::except($validated, ['or_file', 'cr_file'])]
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
            'unit_id'            => $vehicle->unit_id,
            'station_id'         => $vehicle->station_id,
            'assigned_driver_id' => $vehicle->assigned_driver_id,
            'odometer_km'        => $vehicle->odometer_km,
            'next_pms_date'      => optional($vehicle->next_pms_date)->format('Y-m-d'),
            'status'             => $vehicle->status,
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
            'assigned_driver_id' => ['nullable', 'exists:drivers,id'],
            'odometer_km'        => ['nullable', 'integer', 'min:0'],
            'next_pms_date'      => ['nullable', 'date'],
            'status'             => ['required', 'in:SERVICEABLE,UNSERVICEABLE,BER'],
        ];

        if (in_array($role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true)) {
            $rules['unit_id'] = ['required', 'exists:units,id'];
        }

        $validated = $request->validate($rules);
        $validated = $this->applyWriteScope($validated, $user, $role, $vehicle);

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
     * Upload a new year's OR/CR for an existing vehicle (annual re-registration)
     * without touching any of the vehicle's other details.
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
            'or_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'cr_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ], [
            'registration_year.unique' => 'A registration for this year has already been uploaded for this vehicle.',
        ]);

        $vehicle->registrations()->create([
            'or_file_path'      => $request->file('or_file')->store('vehicle_docs/or', 'public'),
            'cr_file_path'      => $request->file('cr_file')->store('vehicle_docs/cr', 'public'),
            'registration_year' => $validated['registration_year'],
            'uploaded_by'       => $user->id,
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
                    'or_url' => route('vehicles.document', ['path' => $reg->or_file_path]),
                    'cr_url' => route('vehicles.document', ['path' => $reg->cr_file_path]),
                    'uploader' => $reg->uploader->fullname ?? 'Unknown User',
                    'date' => $reg->created_at->format('M d, Y h:i A')
                ];
            })
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
