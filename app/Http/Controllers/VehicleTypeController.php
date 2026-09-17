<?php

namespace App\Http\Controllers;

use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VehicleTypeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = VehicleType::withCount('vehicles')->latest();

            // Fast searching
            if ($search = trim($request->input('search.value'))) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $totalRecords = VehicleType::count();
            $filteredRecords = $query->count();

            $start = $request->input('start', 0);
            $length = $request->input('length', 10);

            $vehicleTypes = $query->skip($start)->take($length)->get();

            $data = [];
            foreach ($vehicleTypes as $type) {
                // Name & Description HTML
                $nameHtml = '<div class="type-name">' . e($type->name) . '</div>' .
                            '<div class="type-desc">' . e($type->description ?? '—') . '</div>';

                // Vehicles Count Badge
                $countHtml = '<span class="badge badge-light px-2.5 py-1 border font-weight-bold" style="font-size:12px; border-radius:6px;">' . 
                             '<i class="fas fa-car mr-1 text-primary"></i> ' . $type->vehicles_count . ' units' . 
                             '</span>';

                // Actions HTML
                $actionsHtml = '<div class="text-right">' .
                               '<button class="action-btn mr-1 edit-btn" ' .
                               'data-id="' . $type->id . '" ' .
                               'data-name="' . e($type->name) . '" ' .
                               'data-desc="' . e($type->description) . '"><i class="fas fa-pen"></i></button>' .
                               '<button class="action-btn btn-delete delete-btn" ' .
                               'data-id="' . $type->id . '" ' .
                               'data-name="' . e($type->name) . '" ' .
                               'data-count="' . $type->vehicles_count . '"><i class="fas fa-trash"></i></button>' .
                               '</div>';

                $data[] = [
                    'name_html'  => $nameHtml,
                    'count_html' => $countHtml,
                    'actions_html' => $actionsHtml,
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
            'total_types'    => VehicleType::count(),
            'total_assigned' => VehicleType::withSum('vehicles', 'id')->count(), 
        ];

        return view('vehicle_types.index', compact('stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:vehicle_types,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        VehicleType::create($validated);

        return redirect()->route('vehicle-types.index')->with('success', 'Vehicle category added successfully.');
    }

    public function update(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:vehicle_types,name,' . $vehicleType->id],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $vehicleType->update($validated);

        return redirect()->route('vehicle-types.index')->with('success', 'Vehicle category updated successfully.');
    }

    public function destroy(VehicleType $vehicleType): RedirectResponse
    {
        if ($vehicleType->vehicles()->count() > 0) {
            return redirect()->route('vehicle-types.index')->withErrors(['error' => 'Cannot delete type because vehicles are currently assigned to it.']);
        }

        $vehicleType->delete();
        return redirect()->route('vehicle-types.index')->with('success', 'Vehicle category removed successfully.');
    }
}