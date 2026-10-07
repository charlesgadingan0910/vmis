<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MaintenanceRecord;
use App\Models\TechnicalInspection;
use App\Models\TechnicalInspectionItem;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Digitized Technical Inspection Report checklist — one shared controller for
 * both Maintenance & PMS and Repairs, because a checklist belongs to a
 * maintenance_records row regardless of which module logged it (see
 * MaintenanceRecord::scopeRepairsOnly/scopeExcludingRepairs). It gets its own
 * single route pair (technical-inspections.*) rather than being duplicated
 * under each module's URL prefix — see routes/web.php. Role/visibility
 * handling mirrors MaintenanceController/RepairController.
 */
class TechnicalInspectionController extends Controller
{
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    protected function canAccessVehicle(Vehicle $vehicle, $user, string $role): bool
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
     * Viewers (and every scoped role, within their own unit/station) can look
     * at a checklist read-only — this only blocks someone from reaching a
     * vehicle entirely outside their scope, or a Driver account (which has no
     * branch above and so never matches, same implicit-deny as the other two
     * controllers).
     */
    protected function authorizeView(Vehicle $vehicle): void
    {
        $user = auth()->user();
        $role = $this->role($user);

        if (! $this->canAccessVehicle($vehicle, $user, $role)) {
            abort(403, 'Unauthorized Action');
        }
    }

    protected function authorizeWrite(Vehicle $vehicle): void
    {
        $user = auth()->user();
        $role = $this->role($user);

        if ($role === self::ROLE_VIEWER) {
            abort(403, 'Viewer accounts have read-only access.');
        }
        if (! $this->canAccessVehicle($vehicle, $user, $role)) {
            abort(403, 'Unauthorized Action');
        }
    }

    /**
     * Checklist definition + whatever's already saved for this record (if
     * anything) + this vehicle's component-level history, so the form can
     * show "flagged N times before" hints while it's being filled out — the
     * same data driving the Dashboard's predictive alert, surfaced right
     * where a mechanic can act on it.
     */
    public function edit(MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $this->authorizeView($maintenanceRecord->vehicle);

        $inspection = TechnicalInspection::with('items')
            ->where('maintenance_record_id', $maintenanceRecord->id)
            ->first();

        $savedItems = [];
        if ($inspection) {
            foreach ($inspection->items as $item) {
                // Must match the "\x1F"-joined key componentHistoryForVehicle() uses
                // (and the JS tiKey() helper builds client-side) — every system's
                // "Others" row shares the same component name, so a plain "|" join
                // here would both collide across systems and never match the
                // history lookup's own key format.
                $savedItems[$item->system_category . "\x1F" . $item->component_name] = [
                    'status'  => $item->status,
                    'remarks' => $item->remarks,
                ];
            }
        }

        $history = TechnicalInspection::componentHistoryForVehicle(
            $maintenanceRecord->vehicle_id,
            $inspection?->id
        );

        return response()->json([
            'checklist'               => TechnicalInspection::CHECKLIST,
            'statuses'                => TechnicalInspection::STATUSES,
            'saved_items'             => $savedItems,
            'history'                 => $history,
            'inspected_by'            => $inspection?->inspected_by,
            'witness'                 => $inspection?->witness,
            'inspection_date'         => optional($inspection?->inspection_date)->format('Y-m-d')
                ?? optional($maintenanceRecord->service_date)->format('Y-m-d'),
            'findings_recommendation' => $inspection?->findings_recommendation,
            'has_inspection'          => (bool) $inspection,
            'parts_needed'            => (bool) $maintenanceRecord->parts_needed,
            'stage'                   => $maintenanceRecord->stage,
            'vehicle_label'           => strtoupper($maintenanceRecord->vehicle->plate_number) . ' — ' . trim($maintenanceRecord->vehicle->make . ' ' . $maintenanceRecord->vehicle->model),
        ]);
    }

    /**
     * Upserts the header, then fully replaces the item rows — simplest
     * correct approach, since the whole checklist is always submitted as one
     * payload from the modal and there's no partial-save case to diff
     * against. Blank rows (no status, no remark) are dropped rather than
     * persisted, so a lightly-filled-in checklist doesn't leave ~130 empty
     * rows behind.
     */
    public function save(Request $request, MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $this->authorizeWrite($maintenanceRecord->vehicle);

        $validComponents = [];
        foreach (TechnicalInspection::CHECKLIST as $systemKey => $system) {
            foreach ($system['components'] as $component) {
                $validComponents[] = $systemKey . '|' . $component;
            }
        }

        $validated = $request->validate([
            'inspected_by'             => ['nullable', 'string', 'max:150'],
            'witness'                  => ['nullable', 'string', 'max:150'],
            'inspection_date'          => ['nullable', 'date'],
            'findings_recommendation'  => ['nullable', 'string', 'max:5000'],
            'parts_needed'             => ['nullable', 'boolean'],
            'items'                    => ['array'],
            'items.*.system_category'  => ['required', 'string'],
            'items.*.component_name'   => ['required', 'string'],
            'items.*.status'           => ['nullable', Rule::in(array_keys(TechnicalInspection::STATUSES))],
            'items.*.remarks'          => ['nullable', 'string', 'max:255'],
        ]);

        $wasNew = ! TechnicalInspection::where('maintenance_record_id', $maintenanceRecord->id)->exists();

        $flaggedCount = 0;
        $recordedCount = 0;

        DB::transaction(function () use ($validated, $maintenanceRecord, $validComponents, &$flaggedCount, &$recordedCount) {
            $inspection = TechnicalInspection::updateOrCreate(
                ['maintenance_record_id' => $maintenanceRecord->id],
                [
                    'vehicle_id'              => $maintenanceRecord->vehicle_id,
                    'inspected_by'            => $validated['inspected_by'] ?? null,
                    'witness'                 => $validated['witness'] ?? null,
                    'inspection_date'         => $validated['inspection_date'] ?? $maintenanceRecord->service_date,
                    'findings_recommendation' => $validated['findings_recommendation'] ?? null,
                ]
            );

            $inspection->items()->delete();

            $rows = [];
            $now = now();
            foreach ($validated['items'] ?? [] as $item) {
                // Guards against a tampered/stale payload naming a system/component
                // pair that isn't actually on the official form.
                if (! in_array($item['system_category'] . '|' . $item['component_name'], $validComponents, true)) {
                    continue;
                }
                if (empty($item['status']) && empty($item['remarks'])) {
                    continue;
                }

                $recordedCount++;
                if (! empty($item['status']) && ! in_array($item['status'], TechnicalInspection::OK_STATUSES, true)) {
                    $flaggedCount++;
                }

                $rows[] = [
                    'technical_inspection_id' => $inspection->id,
                    'system_category'         => $item['system_category'],
                    'component_name'          => $item['component_name'],
                    'status'                  => $item['status'] ?: null,
                    'remarks'                 => $item['remarks'] ?: null,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ];
            }

            if (! empty($rows)) {
                TechnicalInspectionItem::insert($rows);
            }

            // Process-flow gate: filling out the checklist is what moves a record
            // out of Requested. Parts flagged as needed route it to Awaiting
            // Parts (Requisition Slip required before it can be Completed);
            // otherwise it's ready to go straight to Completed. Re-saving an
            // already-Completed record's checklist (e.g. to correct a typo)
            // deliberately leaves the stage alone — it's already done, and
            // toggling parts_needed after the fact shouldn't reopen a gate on
            // finished work.
            if ($maintenanceRecord->stage !== MaintenanceRecord::STAGE_COMPLETED) {
                $partsNeeded = (bool) ($validated['parts_needed'] ?? false);
                $maintenanceRecord->update([
                    'parts_needed' => $partsNeeded,
                    'stage'        => $partsNeeded ? MaintenanceRecord::STAGE_AWAITING_PARTS : MaintenanceRecord::STAGE_INSPECTED,
                ]);
            }
        });

        ActivityLog::record(
            $wasNew ? 'created' : 'updated',
            'Technical Inspection',
            ($wasNew ? 'Filled out' : 'Updated') . ' the digital Technical Inspection Report for [' . strtoupper($maintenanceRecord->vehicle->plate_number) . '] (' . $recordedCount . ' item' . ($recordedCount === 1 ? '' : 's') . ' recorded, ' . $flaggedCount . ' flagged).',
            $maintenanceRecord,
            ['after' => ['items_recorded' => $recordedCount, 'items_flagged' => $flaggedCount]]
        );

        // Spell out the next step in the same toast rather than leaving the admin to
        // notice the stage badge/disabled Complete button on their own — this is the
        // #1 point of confusion reported for the process-flow redesign. $maintenanceRecord's
        // in-memory stage/parts_needed already reflect the update() above (or are
        // untouched if it was already Completed, in which case there's no "next step").
        $message = 'Technical Inspection Report checklist saved.';
        if ($maintenanceRecord->stage === MaintenanceRecord::STAGE_AWAITING_PARTS) {
            $message .= ' Parts were flagged as needed — upload the Requisition Slip (the amber button in the table row, or via Edit) before this job can be marked Completed.';
        } elseif ($maintenanceRecord->stage === MaintenanceRecord::STAGE_INSPECTED) {
            $message .= ' This job is now ready to be marked Completed.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
