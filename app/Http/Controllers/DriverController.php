<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Models\Rank;

class DriverController extends Controller
{
    public function index(Request $request)
    {
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
                
                $identityHtml = '<div class="driver-chip-wrapper">' .
                                '<div class="driver-avatar-circle">' . $initials . '</div>' .
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rank'                    => ['nullable', 'string', 'max:50'],
            'firstname'               => ['required', 'string', 'max:50'],
            'middlename'              => ['nullable', 'string', 'max:50'],
            'lastname'                => ['required', 'string', 'max:50'],
            'qlfr'                    => ['nullable', 'string', 'max:20'],
            'license_number'          => ['nullable', 'string', 'max:50', 'unique:drivers,license_number'],
            'license_expiration_date' => ['nullable', 'date'],
            'license_type'            => ['nullable', 'string', 'max:50'],
            'contact_number'          => ['nullable', 'string', 'max:20'],
            'status'                  => ['required', 'in:active,inactive'],
        ]);

        Driver::create($validated);

        return redirect()->route('drivers.index')->with('success', 'Driver registered successfully.');
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $validated = $request->validate([
            'rank'                    => ['nullable', 'string', 'max:50'],
            'firstname'               => ['required', 'string', 'max:50'],
            'middlename'              => ['nullable', 'string', 'max:50'],
            'lastname'                => ['required', 'string', 'max:50'],
            'qlfr'                    => ['nullable', 'string', 'max:20'],
            'license_number'          => ['nullable', 'string', 'max:50', 'unique:drivers,license_number,' . $driver->id],
            'license_expiration_date' => ['nullable', 'date'],
            'license_type'            => ['nullable', 'string', 'max:50'],
            'contact_number'          => ['nullable', 'string', 'max:20'],
            'status'                  => ['required', 'in:active,inactive'],
        ]);

        $driver->update($validated);

        return redirect()->route('drivers.index')->with('success', 'Driver profile updated successfully.');
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        $driver->delete();
        return redirect()->route('drivers.index')->with('success', 'Driver removed successfully.');
    }
}