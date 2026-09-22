<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Models\Rank;

class DriverController extends Controller
{
    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    public function index(Request $request)
    {
        // A driver managing driver profiles (including their own license/
        // contact info, or anyone else's) isn't part of the spec — they log
        // trips for their own assigned vehicle via Trip Logs instead.
        if ($this->role(auth()->user()) === 'DRIVER') {
            abort(403, 'Driver accounts do not have access to Driver Management.');
        }

        if ($request->ajax()) {
            $query = Driver::latest();

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

            $data = [];
            foreach ($drivers as $driver) {
                // Identity HTML
                $initials = strtoupper(substr($driver->firstname, 0, 1) . substr($driver->lastname, 0, 1));
                $middleInitial = $driver->middlename ? substr($driver->middlename, 0, 1) . '.' : '';
                $fullName = trim("{$driver->rank} {$driver->firstname} {$middleInitial} {$driver->lastname} {$driver->qlfr}");

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
                $expiryHtml = '—';
                if ($driver->license_expiration_date) {
                    $formattedDate = $driver->license_expiration_date->format('M d, Y');
                    if ($driver->license_expiration_date < now()->startOfDay()) {
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

                // Actions HTML
                $driverJson = json_encode($driver);
                $actionsHtml = '<div class="text-right">' .
                               '<button class="btn btn-sm btn-light border edit-btn" data-driver=\'' . $driverJson . '\'><i class="fas fa-pen text-secondary"></i></button>' .
                               '<button class="btn btn-sm btn-light border ml-1 delete-btn" data-id="' . $driver->id . '" data-name="' . e($driver->firstname . ' ' . $driver->lastname) . '"><i class="fas fa-trash text-danger"></i></button>' .
                               '</div>';

                $data[] = [
                    'identity_html' => $identityHtml,
                    'license_html'  => $licenseDetailHtml,
                    'expiry_html'   => $expiryHtml,
                    'contact_html'  => $contactHtml,
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

        $stats = [
            'total'    => Driver::count(),
            'active'   => Driver::where('status', 'active')->count(),
            'expired'  => Driver::where('license_expiration_date', '<', now()->startOfDay())->count(),
            'expiring' => Driver::whereBetween('license_expiration_date', [now()->startOfDay(), now()->addDays(30)->endOfDay()])->count(),
        ];

        $ranks = Rank::orderBy('rank_level', 'asc')->get();

        return view('drivers.index', compact('stats', 'ranks'));
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
        $validated = $request->validate([
            'rank'                    => ['required', 'string', 'max:50'],
            'firstname'               => ['required', 'string', 'max:50'],
            'middlename'              => ['nullable', 'string', 'max:50'],
            'lastname'                => ['required', 'string', 'max:50'],
            'qlfr'                    => ['nullable', 'string', 'max:20'],
            'license_number'          => ['nullable', 'string', 'max:50', 'unique:drivers,license_number'],
            'license_expiration_date' => ['nullable', 'date'],
            'license_type'            => ['required', 'string', 'max:50'],
            'contact_number'          => ['required', 'string', 'max:20'],
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
        $validated = $request->validate([
            'rank'                    => ['required', 'string', 'max:50'],
            'firstname'               => ['required', 'string', 'max:50'],
            'middlename'              => ['nullable', 'string', 'max:50'],
            'lastname'                => ['required', 'string', 'max:50'],
            'qlfr'                    => ['nullable', 'string', 'max:20'],
            'license_number'          => ['nullable', 'string', 'max:50', Rule::unique('drivers', 'license_number')->ignore($driver->id)],
            'license_expiration_date' => ['nullable', 'date'],
            'license_type'            => ['required', 'string', 'max:50'],
            'contact_number'          => ['required', 'string', 'max:20'],
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
