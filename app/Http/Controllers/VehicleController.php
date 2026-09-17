<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VehicleController extends Controller
{
    /**
     * Display vehicle inventory with dynamic filters & summary metrics.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Vehicle::with(['driver', 'type'])->latest();

            if ($search = trim($request->input('search.value'))) {
                $query->where(function ($q) use ($search) {
                    $q->where('plate_number', 'like', "%{$search}%")
                      ->orWhere('make', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('engine_number', 'like', "%{$search}%")
                      ->orWhere('chassis_number', 'like', "%{$search}%");
                });
            }

            if ($typeId = $request->get('vehicle_type_id')) {
                $query->where('vehicle_type_id', $typeId);
            }

            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }

            $totalRecords = Vehicle::count();
            $filteredRecords = $query->count();

            $start = $request->input('start', 0);
            $length = $request->input('length', 10);

            $vehicles = $query->skip($start)->take($length)->get();

            $data = [];
            foreach ($vehicles as $vehicle) {
                $plateHtml = '<span class="plate-badge">' . strtoupper($vehicle->plate_number) . '</span>';

                $eng = $vehicle->engine_number ? ' &middot; Eng: ' . e($vehicle->engine_number) : '';
                $specHtml = '<div class="vehicle-main-name">' . e($vehicle->make) . ' ' . e($vehicle->model) . '</div>' .
                            '<div class="vehicle-sub-info">' . (e($vehicle->year_model) ?? 'N/A') . ' &middot; ' . (e($vehicle->color) ?? 'Unspecified') . $eng . '</div>';

                $typeName = $vehicle->type->name ?? ($vehicle->type ?? 'Unspecified');
                $typeHtml = '<span class="badge badge-light px-2 py-1 border" style="font-size:12px; font-weight:600;">' . e($typeName) . '</span>';

                if ($vehicle->driver) {
                    $initials = strtoupper(substr($vehicle->driver->firstname, 0, 1) . substr($vehicle->driver->lastname, 0, 1));
                    
                    // Format middle initial with a trailing period if it exists, otherwise empty string
                    $middleInitial = $vehicle->driver->middlename ? strtoupper(substr($vehicle->driver->middlename, 0, 1)) : '';
                    
                    // Combine into: rank firstname middleInitial lastname qlfr
                    $nameParts = [
                        $vehicle->driver->rank,
                        $vehicle->driver->firstname,
                        $middleInitial,
                        $vehicle->driver->lastname,
                        $vehicle->driver->qlfr
                    ];
                    
                    // Filter out empty/null parts and join with a single space
                    $fullName = implode(' ', array_filter($nameParts, fn($value) => !is_null($value) && trim($value) !== ''));

                    $driverHtml = '<div class="driver-chip-wrapper">' .
                                  '<div class="driver-avatar-circle">' . $initials . '</div>' .
                                  '<div class="driver-name-text">' . e($fullName) . '</div>' .
                                  '</div>';
                } else {
                    $driverHtml = '<span class="driver-none">Unassigned</span>';
                }

                $pmsHtml = '—';
                if ($vehicle->next_pms_date) {
                    $days = now()->startOfDay()->diffInDays($vehicle->next_pms_date->startOfDay(), false);
                    if ($days < 0) {
                        $pmsHtml = '<span class="pms-overdue"><i class="fas fa-exclamation-triangle mr-1"></i>' . $vehicle->next_pms_date->format('M d, Y') . '</span>';
                    } elseif ($days <= 14) {
                        $pmsHtml = '<span class="pms-soon"><i class="fas fa-clock mr-1"></i>' . $vehicle->next_pms_date->format('M d, Y') . '</span>';
                    } else {
                        $pmsHtml = '<span class="pms-normal">' . $vehicle->next_pms_date->format('M d, Y') . '</span>';
                    }
                }

                // Status HTML Mapping for SERVICEABLE, UNSERVICEABLE, BER
                $statusClass = strtolower($vehicle->status);
                $statusHtml = '<span class="status-pill status-' . $statusClass . '">' .
                              '<span class="dot"></span>' . $vehicle->status . '</span>';

                $data[] = [
                    'plate_html'  => $plateHtml,
                    'spec_html'   => $specHtml,
                    'type_html'   => $typeHtml,
                    'driver_html' => $driverHtml,
                    'pms_html'    => $pmsHtml,
                    'status_html' => $statusHtml,
                ];
            }

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $data,
            ]);
        }

        // Updated summary metrics for SERVICEABLE, UNSERVICEABLE, BER
        $stats = [
            'total'         => Vehicle::count(),
            'serviceable'   => Vehicle::where('status', 'SERVICEABLE')->count(),
            'unserviceable' => Vehicle::where('status', 'UNSERVICEABLE')->count(),
            'ber'           => Vehicle::where('status', 'BER')->count(),
        ];

        $drivers = Driver::where('status', 'active')->orderBy('lastname')->get();
        $vehicleTypes = VehicleType::orderBy('name')->get();

        return view('vehicles.index', compact('stats', 'drivers', 'vehicleTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plate_number'       => ['required', 'string', 'max:20', 'unique:vehicles,plate_number'],
            'engine_number'      => ['nullable', 'string', 'max:50', 'unique:vehicles,engine_number'],
            'chassis_number'     => ['nullable', 'string', 'max:50', 'unique:vehicles,chassis_number'],
            'make'               => ['required', 'string', 'max:50'],
            'model'              => ['required', 'string', 'max:50'],
            'vehicle_type_id'    => ['required', 'exists:vehicle_types,id'],
            'year_model'         => ['nullable', 'integer', 'min:1980', 'max:' . (date('Y') + 1)],
            'color'              => ['nullable', 'string', 'max:30'],
            'acquisition_date'   => ['nullable', 'date'],
            'assigned_driver_id' => ['nullable', 'exists:drivers,id'],
            'odometer_km'        => ['nullable', 'integer', 'min:0'],
            'next_pms_date'      => ['nullable', 'date'],
            'status'             => ['required', 'in:SERVICEABLE,UNSERVICEABLE,BER'], // <-- Updated validation rule
        ]);

        $validated['qr_code'] = Str::upper(Str::random(10));
        $validated['is_active'] = '1';

        Vehicle::create($validated);

        return redirect()->route('vehicles.index')
            ->with('success', 'Vehicle [' . strtoupper($validated['plate_number']) . '] registered successfully!');
    }

    /**
     * Real-time AJAX Duplicate Field Checker.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $field = $request->input('field'); // plate_number, engine_number, chassis_number
        $value = strtoupper(trim($request->input('value')));

        if (!in_array($field, ['plate_number', 'engine_number', 'chassis_number']) || empty($value)) {
            return response()->json(['exists' => false]);
        }

        $existingRecord = Vehicle::where($field, $value)->first();

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
}