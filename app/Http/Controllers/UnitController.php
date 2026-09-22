<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Station;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class UnitController extends Controller
{
    protected const ALLOWED_ROLES = ['SUPER ADMINISTRATOR', 'ADMINISTRATOR'];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Per this task's spec: Units & Stations management is exclusive to the
     * Super Administrator and Administrator roles — Unit/Station Administrators
     * and Viewers are blocked from this page entirely. Same full-page abort
     * pattern as ActivityLogController::authorizeAccess(), just for two
     * allowed roles instead of one.
     */
    protected function authorizeAccess(): void
    {
        if (!in_array($this->role(auth()->user()), self::ALLOWED_ROLES, true)) {
            abort(403, 'Only the Super Administrator and Administrator can manage units and stations.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAccess();

        if ($request->ajax()) {
            $query = Unit::withCount('stations')->orderBy('unit_name');

            if ($search = trim($request->input('search.value'))) {
                $query->where(function ($q) use ($search) {
                    $q->where('unit_name', 'like', "%{$search}%")
                      ->orWhere('unit_abbvr', 'like', "%{$search}%");
                });
            }

            $totalRecords = Unit::count();
            $filteredRecords = $query->count();

            $start = $request->input('start', 0);
            $length = $request->input('length', 10);

            $units = $query->skip($start)->take($length)->get();

            $data = [];
            foreach ($units as $unit) {
                $nameHtml = '<div class="type-name">' . e($unit->unit_name ?: 'Unnamed Unit') . '</div>' .
                            ($unit->unit_abbvr ? '<div class="type-desc">' . e($unit->unit_abbvr) . '</div>' : '');

                $countHtml = '<span class="badge badge-light px-2 py-1 border font-weight-bold" style="font-size:12px; border-radius:6px;">' .
                             '<i class="fas fa-map-pin mr-1 text-primary"></i> ' . $unit->stations_count . ' station' . ($unit->stations_count === 1 ? '' : 's') .
                             '</span>';

                $actionsHtml = '<div class="text-right">' .
                               '<button class="action-btn mr-1 edit-unit-btn" ' .
                               'data-id="' . $unit->id . '" ' .
                               'data-name="' . e($unit->unit_name ?? '') . '" ' .
                               'data-abbvr="' . e($unit->unit_abbvr ?? '') . '"><i class="fas fa-pen"></i></button>' .
                               '<button class="action-btn btn-delete delete-unit-btn" ' .
                               'data-id="' . $unit->id . '" ' .
                               'data-name="' . e($unit->unit_name ?: 'this unit') . '" ' .
                               'data-count="' . $unit->stations_count . '"><i class="fas fa-trash"></i></button>' .
                               '</div>';

                $data[] = [
                    'name_html'    => $nameHtml,
                    'count_html'   => $countHtml,
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
            // Registry size — the two counts a reader needs before anything else.
            'total_units'    => Unit::count(),
            'total_stations' => Station::count(),
            // How much of the org chart is actually populated with real
            // people/vehicles versus still just empty scaffolding.
            'personnel_assigned' => User::whereNotNull('unit_id')->count(),
            'vehicles_deployed'  => Vehicle::whereNotNull('unit_id')->count(),
        ];

        $units = Unit::orderBy('unit_name')->get();

        return view('units_stations.index', compact('stats', 'units'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'unit_name'  => ['required', 'string', 'max:100', 'unique:units,unit_name'],
            'unit_abbvr' => ['nullable', 'string', 'max:20', 'unique:units,unit_abbvr'],
        ]);

        $unit = Unit::create($validated);

        ActivityLog::record(
            'created',
            'Unit',
            'Added unit [' . $unit->unit_name . '].',
            $unit,
            ['after' => $validated]
        );

        return redirect()->route('units.index')->with('success', 'Unit added successfully.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'unit_name'  => ['required', 'string', 'max:100', 'unique:units,unit_name,' . $unit->id],
            'unit_abbvr' => ['nullable', 'string', 'max:20', 'unique:units,unit_abbvr,' . $unit->id],
        ]);

        $before = $unit->getOriginal();
        $unit->update($validated);
        $changed = $unit->getChanges();
        unset($changed['updated_at']);

        if (!empty($changed)) {
            ActivityLog::record(
                'updated',
                'Unit',
                'Updated unit [' . $unit->unit_name . '].',
                $unit,
                ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
            );
        }

        return redirect()->route('units.index')->with('success', 'Unit updated successfully.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorizeAccess();

        if ($unit->stations()->count() > 0) {
            return redirect()->route('units.index')->withErrors(['error' => 'Cannot delete "' . $unit->unit_name . '" because stations are still assigned to it. Reassign or remove them first.']);
        }

        if (User::where('unit_id', $unit->id)->exists() || Vehicle::where('unit_id', $unit->id)->exists()) {
            return redirect()->route('units.index')->withErrors(['error' => 'Cannot delete "' . $unit->unit_name . '" because personnel or vehicles are still assigned to it. Reassign them first.']);
        }

        $snapshot = $unit->toArray();
        $name = $unit->unit_name;
        $unit->delete();

        ActivityLog::record('deleted', 'Unit', 'Removed unit [' . $name . '].', $unit, ['before' => $snapshot]);

        return redirect()->route('units.index')->with('success', 'Unit removed successfully.');
    }
}
