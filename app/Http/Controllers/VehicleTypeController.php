<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
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
                               'data-desc="' . e($type->description ?? '') . '"><i class="fas fa-pen"></i></button>' .
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
            // Total classification categories on file.
            'total_types'   => VehicleType::count(),
            // Vehicles that currently have a category assigned.
            'classified'    => Vehicle::whereNotNull('vehicle_type_id')->count(),
            // Vehicles with no category at all — worth flagging so they don't
            // get missed when someone is auditing the fleet by type.
            'unclassified'  => Vehicle::whereNull('vehicle_type_id')->count(),
            // Categories nobody has actually used yet.
            'unused_types'  => VehicleType::doesntHave('vehicles')->count(),
        ];

        return view('vehicle_types.index', compact('stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:vehicle_types,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $vehicleType = VehicleType::create($validated);

        ActivityLog::record(
            'created',
            'Vehicle Type',
            'Added vehicle category [' . $vehicleType->name . '].',
            $vehicleType,
            ['after' => $validated]
        );

        return redirect()->route('vehicle-types.index')->with('success', 'Vehicle category added successfully.');
    }

    public function update(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:vehicle_types,name,' . $vehicleType->id],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $before = $vehicleType->getOriginal();
        $vehicleType->update($validated);
        $changed = $vehicleType->getChanges();
        unset($changed['updated_at']);

        if (!empty($changed)) {
            ActivityLog::record(
                'updated',
                'Vehicle Type',
                'Updated vehicle category [' . $vehicleType->name . '].',
                $vehicleType,
                ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
            );
        }

        return redirect()->route('vehicle-types.index')->with('success', 'Vehicle category updated successfully.');
    }

    public function destroy(VehicleType $vehicleType): RedirectResponse
    {
        if ($vehicleType->vehicles()->count() > 0) {
            return redirect()->route('vehicle-types.index')->withErrors(['error' => 'Cannot delete type because vehicles are currently assigned to it.']);
        }

        $snapshot = $vehicleType->toArray();
        $name = $vehicleType->name;
        $vehicleType->delete();

        ActivityLog::record('deleted', 'Vehicle Type', 'Removed vehicle category [' . $name . '].', $vehicleType, ['before' => $snapshot]);

        return redirect()->route('vehicle-types.index')->with('success', 'Vehicle category removed successfully.');
    }
}