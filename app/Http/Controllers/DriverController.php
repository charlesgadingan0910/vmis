<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Models\Rank;

/**
 * Driver Management — and, as of the Driver Assignments merge below, the
 * ONLY place vehicle assignment is managed too. There used to be a
 * separate "Driver Assignments" page (vehicle-centric: pick a vehicle,
 * assign it a driver); per request, that workflow now lives as an extra
 * column + actions right on this page instead, so nobody has to leave
 * Driver Management to see or change who drives what. The actual
 * assign/unassign/history logic still lives in DriverAssignmentController
 * (its driver-centric methods — assignToDriver()/unassignDriver()/
 * driverHistory() — written for this merge), called via AJAX from here;
 * this controller only adds the extra read-side data (each driver's
 * current vehicle(s), and the fleet list for the Assign modal's picker).
 */
class DriverController extends Controller
{
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->role($user);

        // A driver managing driver profiles (including their own license/
        // contact info, or anyone else's) isn't part of the spec — they log
        // trips for their own assigned vehicle via Trip Logs instead.
        if ($role === 'DRIVER') {
            abort(403, 'Driver accounts do not have access to Driver Management.');
        }

        if ($request->ajax()) {
            return $this->ajaxList($request, $role);
        }

        // A vehicle can have two assigned drivers (slot 1 and slot 2), so
        // "currently assigned" pulls distinct driver ids from both columns
        // — otherwise a driver who's only ever in slot 2 would wrongly
        // count as unassigned here.
        $assignedDriverIds = Vehicle::excludingDisposed()->whereNotNull('assigned_driver_id')->distinct()->pluck('assigned_driver_id')
            ->merge(Vehicle::excludingDisposed()->whereNotNull('assigned_driver_id_2')->distinct()->pluck('assigned_driver_id_2'))
            ->unique();
        $totalDrivers = Driver::count();

        $stats = [
            'total'           => $totalDrivers,
            'active'          => Driver::where('status', 'active')->count(),
            'expired'         => Driver::where('license_expiration_date', '<', now()->startOfDay())->count(),
            'expiring'        => Driver::whereBetween('license_expiration_date', [now()->startOfDay(), now()->addDays(30)->endOfDay()])->count(),
            'with_vehicle'    => Driver::whereIn('id', $assignedDriverIds)->count(),
        ];
        $stats['without_vehicle'] = $totalDrivers - $stats['with_vehicle'];

        $ranks = Rank::orderBy('rank_level', 'asc')->get();
        $dlCodes = Driver::DL_CODES;
        $restrictionCodes = Driver::RESTRICTION_CODES;

        // Vehicle Assignment picker data — scoped the same way Vehicle
        // Inventory is: a Unit/Station Administrator only ever sees (and
        // may assign/reassign) vehicles inside their own unit/station;
        // every other non-Driver role sees the whole fleet. VIEWER can see
        // the page (read-only) but the Assign/Unassign buttons are hidden
        // for them client-side, and DriverAssignmentController rejects the
        // write server-side regardless.
        $canManageAssignments = $role !== self::ROLE_VIEWER;

        $vehicleQuery = Vehicle::with(['driver', 'unit', 'station'])->excludingDisposed();
        if ($role === self::ROLE_UNIT_ADMIN) {
            $vehicleQuery->where('unit_id', $user->unit_id);
        } elseif ($role === self::ROLE_STATION_ADMIN) {
            $vehicleQuery->where('station_id', $user->station_id);
        }
        $vehicles = $vehicleQuery->orderBy('plate_number')->get();

        return view('drivers.index', compact('stats', 'ranks', 'dlCodes', 'restrictionCodes', 'vehicles', 'canManageAssignments'));
    }

    protected function ajaxList(Request $request, string $role): JsonResponse
    {
        $query = Driver::with(['vehicles' => function ($q) {
            $q->excludingDisposed();
        }])->latest();

        // Handle Search
        if ($search = trim($request->input('search.value'))) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('license_number', 'like', "%{$search}%")
                  ->orWhere('contact_number', 'like', "%{$search}%");
            });
        }

        // Handle Status Filter
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $totalRecords = Driver::count();
        $filteredRecords = $query->count();

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);

        $drivers = $query->skip($start)->take($length)->get();

        // VIEWER never gets the Assign/Reassign/Unassign buttons — same
        // read-only boundary DriverAssignmentController enforces server-side.
        $canManageAssignments = $role !== self::ROLE_VIEWER;

        $data = [];
        foreach ($drivers as $driver) {
            // Identity HTML
            $initials = strtoupper(substr($driver->firstname, 0, 1) . substr($driver->lastname, 0, 1));
            $middleInitial = $driver->middlename ? substr($driver->middlename, 0, 1) . '.' : '';
            $fullName = trim("{$driver->rank} {$driver->firstname} {$middleInitial} {$driver->lastname} {$driver->qlfr}");
            $plainName = trim($driver->firstname . ' ' . $driver->lastname);

            // Real photo when we have one, initials avatar otherwise — matches the
            // fallback pattern already used elsewhere in the app (vehicle driver chips).
            $avatarHtml = $driver->photo_path
                ? '<img src="' . route('drivers.photo', $driver) . '" class="driver-avatar-circle" alt="' . e($fullName) . '">'
                : '<div class="driver-avatar-circle">' . $initials . '</div>';

            $identityHtml = '<div class="driver-chip-wrapper">' .
                            $avatarHtml .
                            '<div><div class="driver-name-text">' . e($fullName) . '</div>' .
                            '<small class="text-muted text-uppercase">' . e($driver->status) . '</small></div>' .
                            '</div>';

            // License Detail HTML
            $licenseDetailHtml = '<div class="font-weight-bold text-secondary">' . e($driver->license_number ?? 'Not Provided') . '</div>' .
                                 '<small class="text-muted">' . e($driver->license_type ?? 'Type Unspecified') . '</small>';

            // Expiration Status HTML
            $isLicenseExpired = $driver->isLicenseExpired();
            $expiryHtml = '—';
            if ($driver->license_expiration_date) {
                $formattedDate = $driver->license_expiration_date->format('M d, Y');
                if ($isLicenseExpired) {
                    $expiryHtml = '<div>' . $formattedDate . '</div>' .
                                  '<span class="badge-monitor monitor-expired mt-1"><i class="fas fa-times-circle"></i> Expired</span>';
                } elseif ($driver->license_expiration_date <= now()->addDays(30)->endOfDay()) {
                    $expiryHtml = '<div>' . $formattedDate . '</div>' .
                                  '<span class="badge-monitor monitor-expiring mt-1"><i class="fas fa-exclamation-circle"></i> Expiring Soon</span>';
                } else {
                    $expiryHtml = '<div>' . $formattedDate . '</div>' .
                                  '<span class="badge-monitor monitor-valid mt-1"><i class="fas fa-check-circle"></i> Valid</span>';
                }
            } else {
                $expiryHtml = '<div>—</div><span class="badge-monitor bg-light border text-muted mt-1"><i class="fas fa-minus"></i> No Expiry Set</span>';
            }

            // Contact HTML
            $contactHtml = e($driver->contact_number ?? '—');

            // Vehicle Assignment HTML — folded in from the former standalone
            // Driver Assignments page (see this controller's class docblock).
            // $driver->vehicles was eager-loaded above already filtered to
            // excludingDisposed(), so this is never an N+1.
            $currentVehicle = $driver->vehicles->first();
            if ($driver->vehicles->isNotEmpty()) {
                $vehicleHtml = $driver->vehicles->map(function ($v) {
                    return '<span class="plate-badge">' . e(strtoupper($v->plate_number)) . '</span>' .
                           '<div class="vehicle-sub-info mt-1">' . e(trim($v->make . ' ' . $v->model)) . '</div>';
                })->implode('<hr class="my-2" style="border:none;border-top:1px dashed #e2e8f0;">');
            } else {
                $vehicleHtml = '<span class="badge badge-light text-dark">Unassigned</span>';
            }

            // Actions HTML
            $driverJson = json_encode($driver);

            $assignmentButtons = '';
            if ($canManageAssignments) {
                $assignmentButtons .= '<button type="button" class="btn btn-sm btn-primary assign-vehicle-btn" ' .
                    'data-id="' . $driver->id . '" data-name="' . e($plainName) . '" ' .
                    'data-expired="' . ($isLicenseExpired ? '1' : '0') . '" ' .
                    'data-current-vehicle="' . ($currentVehicle->id ?? '') . '" ' .
                    'data-current-vehicle-plate="' . ($currentVehicle ? e(strtoupper($currentVehicle->plate_number)) : '') . '">' .
                    ($currentVehicle ? 'Reassign' : 'Assign') . '</button> ';

                if ($currentVehicle) {
                    $assignmentButtons .= '<button type="button" class="btn btn-sm btn-outline-danger ml-1 unassign-vehicle-btn" data-id="' . $driver->id . '" data-name="' . e($plainName) . '">Unassign</button> ';
                }
            }
            $assignmentButtons .= '<button type="button" class="btn btn-sm btn-light border ml-1 vehicle-history-btn" data-id="' . $driver->id . '" data-name="' . e($plainName) . '" title="Vehicle assignment history"><i class="fas fa-history"></i></button> ';

            $actionsHtml = '<div class="text-right text-nowrap">' .
                           $assignmentButtons .
                           '<button class="btn btn-sm btn-light border ml-1 edit-btn" data-driver=\'' . $driverJson . '\'><i class="fas fa-pen text-secondary"></i></button>' .
                           '<button class="btn btn-sm btn-light border ml-1 delete-btn" data-id="' . $driver->id . '" data-name="' . e($plainName) . '"><i class="fas fa-trash text-danger"></i></button>' .
                           '</div>';

            $data[] = [
                'identity_html' => $identityHtml,
                'license_html'  => $licenseDetailHtml,
                'expiry_html'   => $expiryHtml,
                'contact_html'  => $contactHtml,
                'vehicle_html'  => $vehicleHtml,
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

    /**
     * Live duplicate check fired right after a license scan extracts a license
     * number — lets the registration form warn (and reset itself) before the
     * user fills out the rest of the form for someone already on file.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $licenseNumber = strtoupper(trim((string) $request->input('license_number', '')));

        if ($licenseNumber === '') {
            return response()->json(['exists' => false]);
        }

        $existing = Driver::where('license_number', $licenseNumber)->first();

        if ($existing) {
            $name = trim($existing->rank . ' ' . $existing->firstname . ' ' . $existing->lastname);

            return response()->json([
                'exists'      => true,
                'driver_name' => $name,
                'status'      => $existing->status,
            ]);
        }

        return response()->json(['exists' => false]);
    }

    /**
     * Streams a driver's profile photo. Kept behind auth like the vehicle OR/CR
     * documents, and read directly off the disk rather than through the
     * public/storage symlink (unreliable on Windows/WAMP).
     */
    public function photo(Driver $driver)
    {
        if (! $driver->photo_path || ! Storage::disk('public')->exists($driver->photo_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($driver->photo_path);
    }

    public function store(Request $request): RedirectResponse
    {
        // Unchecked checkbox groups aren't submitted at all, so normalize them
        // to an empty array rather than letting the key go missing.
        $request->merge([
            'dl_codes'          => $request->input('dl_codes', []),
            'restriction_codes' => $request->input('restriction_codes', []),
        ]);

        $validated = $request->validate([
            'rank'                    => ['required', 'string', 'max:50'],
            'firstname'               => ['required', 'string', 'max:50'],
            'middlename'              => ['nullable', 'string', 'max:50'],
            'lastname'                => ['required', 'string', 'max:50'],
            'qlfr'                    => ['nullable', 'string', 'max:20'],
            'license_number'          => ['nullable', 'string', 'max:50', 'unique:drivers,license_number'],
            'license_expiration_date' => ['nullable', 'date'],
            'license_type'            => ['required', 'string', 'max:50'],
            'dl_codes'                => ['nullable', 'array'],
            'dl_codes.*'              => ['string', Rule::in(array_keys(Driver::DL_CODES))],
            'restriction_codes'       => ['nullable', 'array'],
            'restriction_codes.*'     => ['string', Rule::in(array_keys(Driver::RESTRICTION_CODES))],
            'license_serial_number'   => ['nullable', 'string', 'max:50'],
            'contact_number'          => ['required', 'string', 'max:20'],
            'emergency_contact_name'    => ['nullable', 'string', 'max:100'],
            'emergency_contact_address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number'  => ['nullable', 'string', 'max:20'],
            'status'                  => ['required', 'in:active,inactive'],
            'photo'                   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('driver_photos', 'public');
        }

        $driver = Driver::create($validated);

        ActivityLog::record(
            'created',
            'Driver',
            'Registered driver ' . trim($driver->firstname . ' ' . $driver->lastname) . '.',
            $driver,
            ['after' => Arr::except($validated, ['photo'])]
        );

        return redirect()->route('drivers.index')->with('success', 'Driver registered successfully.');
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        // Unchecked checkbox groups aren't submitted at all, so normalize them
        // to an empty array rather than leaving the driver's old codes in place.
        $request->merge([
            'dl_codes'          => $request->input('dl_codes', []),
            'restriction_codes' => $request->input('restriction_codes', []),
        ]);

        $validated = $request->validate([
            'rank'                    => ['required', 'string', 'max:50'],
            'firstname'               => ['required', 'string', 'max:50'],
            'middlename'              => ['nullable', 'string', 'max:50'],
            'lastname'                => ['required', 'string', 'max:50'],
            'qlfr'                    => ['nullable', 'string', 'max:20'],
            'license_number'          => ['nullable', 'string', 'max:50', Rule::unique('drivers', 'license_number')->ignore($driver->id)],
            'license_expiration_date' => ['nullable', 'date'],
            'license_type'            => ['required', 'string', 'max:50'],
            'dl_codes'                => ['nullable', 'array'],
            'dl_codes.*'              => ['string', Rule::in(array_keys(Driver::DL_CODES))],
            'restriction_codes'       => ['nullable', 'array'],
            'restriction_codes.*'     => ['string', Rule::in(array_keys(Driver::RESTRICTION_CODES))],
            'license_serial_number'   => ['nullable', 'string', 'max:50'],
            'contact_number'          => ['required', 'string', 'max:20'],
            'emergency_contact_name'    => ['nullable', 'string', 'max:100'],
            'emergency_contact_address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number'  => ['nullable', 'string', 'max:20'],
            'status'                  => ['required', 'in:active,inactive'],
            'photo'                   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_photo'            => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('photo')) {
            // A new photo always wins over an explicit "remove" request from the
            // same submission — replace, don't just delete.
            if ($driver->photo_path) {
                Storage::disk('public')->delete($driver->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('driver_photos', 'public');
        } elseif ($request->boolean('remove_photo') && $driver->photo_path) {
            Storage::disk('public')->delete($driver->photo_path);
            $validated['photo_path'] = null;
        }

        unset($validated['remove_photo']); // never a real column — just a UI signal

        $before = $driver->getOriginal();
        $driver->update($validated);
        $changed = $driver->getChanges();
        unset($changed['updated_at']);

        if (!empty($changed)) {
            ActivityLog::record(
                'updated',
                'Driver',
                'Updated driver ' . trim($driver->firstname . ' ' . $driver->lastname) . '.',
                $driver,
                ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
            );
        }

        return redirect()->route('drivers.index')->with('success', 'Driver profile updated successfully.');
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        if ($driver->photo_path) {
            Storage::disk('public')->delete($driver->photo_path);
        }

        $snapshot = $driver->toArray();
        $name = trim($driver->firstname . ' ' . $driver->lastname);
        $driver->delete();

        ActivityLog::record('deleted', 'Driver', 'Removed driver ' . $name . '.', $driver, ['before' => $snapshot]);

        return redirect()->route('drivers.index')->with('success', 'Driver removed successfully.');
    }
}
