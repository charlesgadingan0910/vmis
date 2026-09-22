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

class StationController extends Controller
{
    protected const ALLOWED_ROLES = ['SUPER ADMINISTRATOR', 'ADMINISTRATOR'];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Same access rule as UnitController — the two live on one combined
     * management page, so they share the same restriction.
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
            $query = Station::with('unit')->orderBy('station_name');

            if ($search = trim($request->input('search.value'))) {
                $query->where(function ($q) use ($search) {
                    $q->where('station_name', 'like', "%{$search}%")
                      ->orWhere('station_abbvr', 'like', "%{$search}%")
                      ->orWhereHas('unit', function ($uq) use ($search) {
                          $uq->where('unit_name', 'like', "%{$search}%");
                      });
                });
            }

            $totalRecords = Station::count();
            $filteredRecords = $query->count();

            $start = $request->input('start', 0);
            $length = $request->input('length', 10);

            $stations = $query->skip($start)->take($length)->get();

            $data = [];
            foreach ($stations as $station) {
                $nameHtml = '<div class="type-name">' . e($station->station_name ?: 'Unnamed Station') . '</div>' .
                            '<div class="type-desc">' . e($station->station_abbvr) . '</div>';

                $unitHtml = $station->unit
                    ? '<span class="badge badge-light border">' . e($station->unit->unit_name) . '</span>'
                    : '<span class="text-muted small font-italic">Unassigned</span>';

                $inUseCount = Vehicle::where('station_id', $station->id)->count()
                            + User::where('station_id', $station->id)->count();

                $actionsHtml = '<div class="text-right">' .
                               '<button class="action-btn mr-1 edit-station-btn" ' .
                               'data-id="' . $station->id . '" ' .
                               'data-name="' . e($station->station_name ?? '') . '" ' .
                               'data-abbvr="' . e($station->station_abbvr) . '" ' .
                               'data-unit-id="' . $station->unit_id . '"><i class="fas fa-pen"></i></button>' .
                               '<button class="action-btn btn-delete delete-station-btn" ' .
                               'data-id="' . $station->id . '" ' .
                               'data-name="' . e($station->station_name ?: 'this station') . '" ' .
                               'data-count="' . $inUseCount . '"><i class="fas fa-trash"></i></button>' .
                               '</div>';

                $data[] = [
                    'name_html'    => $nameHtml,
                    'unit_html'    => $unitHtml,
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

        // The Stations table lives on the combined Units & Stations page —
        // there is no standalone stations view to render.
        return redirect()->route('units.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'unit_id'       => ['required', 'exists:units,id'],
            'station_name'  => ['required', 'string', 'max:100'],
            'station_abbvr' => ['required', 'string', 'max:20', 'unique:stations,station_abbvr'],
        ]);

        $station = Station::create($validated);

        ActivityLog::record(
            'created',
            'Station',
            'Added station [' . $station->station_name . '].',
            $station,
            ['after' => $validated]
        );

        return redirect()->route('units.index')->with('success', 'Station added successfully.');
    }

    public function update(Request $request, Station $station): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'unit_id'       => ['required', 'exists:units,id'],
            'station_name'  => ['required', 'string', 'max:100'],
            'station_abbvr' => ['required', 'string', 'max:20', 'unique:stations,station_abbvr,' . $station->id],
        ]);

        $before = $station->getOriginal();
        $station->update($validated);
        $changed = $station->getChanges();
        unset($changed['updated_at']);

        if (!empty($changed)) {
            ActivityLog::record(
                'updated',
                'Station',
                'Updated station [' . $station->station_name . '].',
                $station,
                ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
            );
        }

        return redirect()->route('units.index')->with('success', 'Station updated successfully.');
    }

    public function destroy(Station $station): RedirectResponse
    {
        $this->authorizeAccess();

        $inUseCount = Vehicle::where('station_id', $station->id)->count()
                    + User::where('station_id', $station->id)->count();

        if ($inUseCount > 0) {
            return redirect()->route('units.index')->withErrors(['error' => 'Cannot delete "' . $station->station_name . '" because personnel or vehicles are still assigned to it. Reassign them first.']);
        }

        $snapshot = $station->toArray();
        $name = $station->station_name;
        $station->delete();

        ActivityLog::record('deleted', 'Station', 'Removed station [' . $name . '].', $station, ['before' => $snapshot]);

        return redirect()->route('units.index')->with('success', 'Station removed successfully.');
    }
}
