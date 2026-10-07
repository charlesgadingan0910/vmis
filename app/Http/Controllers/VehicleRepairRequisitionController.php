<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use App\Models\VehicleRepairRequisition;
use App\Models\VehicleRepairRequisitionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Digitized Vehicle Repair Requisition Slip — Step 3 of the process flow,
 * filled out once the Technical Inspection has flagged that parts/materials
 * are needed (MaintenanceRecord::canFillRequisition()). Same shared-controller
 * shape as TechnicalInspectionController: one route pair for both
 * Maintenance & PMS and Repairs, since the slip belongs to the underlying
 * maintenance_records row regardless of which module logged it. Filling
 * this out (not uploading a scanned copy) is what MaintenanceRecord::
 * hasRequisitionFilled() checks before a record can be marked Completed.
 */
class VehicleRepairRequisitionController extends Controller
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
     * Existing requisition (if any) + sensible defaults pulled from the
     * vehicle/record so the form doesn't start completely blank — the admin
     * filling this out already told the system most of this via the request
     * and the technical inspection.
     */
    public function edit(MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $this->authorizeView($maintenanceRecord->vehicle);

        $requisition = VehicleRepairRequisition::with('items')
            ->where('maintenance_record_id', $maintenanceRecord->id)
            ->first();

        $vehicle = $maintenanceRecord->vehicle;
        $driverName = null;
        if ($vehicle && $vehicle->driver) {
            $driverName = trim($vehicle->driver->firstname . ' ' . $vehicle->driver->lastname);
        }

        return response()->json([
            'requisition_no'      => $requisition?->requisition_no,
            'requisition_date'    => optional($requisition?->requisition_date)->format('Y-m-d') ?? now()->format('Y-m-d'),
            'office_unit'         => $requisition?->office_unit ?? optional($vehicle?->unit)->unit_name,
            'driver_custodian'    => $requisition?->driver_custodian ?? $driverName,
            'current_mileage'     => $requisition?->current_mileage ?? $vehicle?->odometer_km,
            'reason_for_request'  => $requisition?->reason_for_request ?? $maintenanceRecord->description,
            'findings_diagnosis'  => $requisition?->findings_diagnosis,
            'requested_by'        => $requisition?->requested_by ?? $maintenanceRecord->requested_by,
            'inspected_by'        => $requisition?->inspected_by,
            'repair_conducted'    => $requisition?->repair_conducted,
            'mechanics_in_charge' => $requisition?->mechanics_in_charge,
            'items'               => $requisition
                ? $requisition->items->map(fn ($i) => [
                    'qty'         => $i->qty,
                    'particulars' => $i->particulars,
                    'price'       => $i->price,
                ])->values()
                : [],
            'total'          => $requisition ? $requisition->totalPrice() : 0,
            'has_requisition' => (bool) $requisition,
            'can_fill'        => $maintenanceRecord->canFillRequisition(),
            'vehicle_label'   => $vehicle ? strtoupper($vehicle->plate_number) . ' — ' . trim($vehicle->make . ' ' . $vehicle->model) : '',
        ]);
    }

    /**
     * Upserts the header, then fully replaces the item rows — same
     * simplest-correct approach as TechnicalInspectionController::save()
     * (the whole slip is always submitted as one payload from the modal).
     * Blank rows (no particulars) are dropped rather than persisted.
     */
    public function save(Request $request, MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $this->authorizeWrite($maintenanceRecord->vehicle);
        abort_unless($maintenanceRecord->canFillRequisition(), 422, 'The Technical Inspection has not flagged parts as needed yet.');

        $validated = $request->validate([
            'requisition_date'    => ['nullable', 'date'],
            'office_unit'         => ['nullable', 'string', 'max:150'],
            'driver_custodian'    => ['nullable', 'string', 'max:150'],
            'current_mileage'     => ['nullable', 'integer', 'min:0'],
            'reason_for_request'  => ['nullable', 'string', 'max:2000'],
            'findings_diagnosis'  => ['nullable', 'string', 'max:2000'],
            'requested_by'        => ['nullable', 'string', 'max:150'],
            'inspected_by'        => ['nullable', 'string', 'max:150'],
            'repair_conducted'    => ['nullable', 'string', 'max:2000'],
            'mechanics_in_charge' => ['nullable', 'string', 'max:1000'],
            'items'               => ['array'],
            'items.*.qty'         => ['nullable', 'integer', 'min:0'],
            'items.*.particulars' => ['nullable', 'string', 'max:255'],
            'items.*.price'       => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $wasNew = ! VehicleRepairRequisition::where('maintenance_record_id', $maintenanceRecord->id)->exists();
        $itemCount = 0;

        $requisition = DB::transaction(function () use ($validated, $maintenanceRecord, &$itemCount) {
            $requisition = VehicleRepairRequisition::updateOrCreate(
                ['maintenance_record_id' => $maintenanceRecord->id],
                array_merge(Arr::except($validated, ['items']), ['vehicle_id' => $maintenanceRecord->vehicle_id])
            );

            if (! $requisition->requisition_no) {
                $requisition->update(['requisition_no' => 'RS-' . str_pad((string) $requisition->id, 6, '0', STR_PAD_LEFT)]);
            }

            $requisition->items()->delete();

            $rows = [];
            $now = now();
            foreach ($validated['items'] ?? [] as $item) {
                $particulars = trim((string) ($item['particulars'] ?? ''));
                if ($particulars === '') {
                    continue;
                }
                $itemCount++;
                $rows[] = [
                    'vehicle_repair_requisition_id' => $requisition->id,
                    'qty'         => $item['qty'] ?? null,
                    'particulars' => $particulars,
                    'price'       => $item['price'] ?? null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
            if (! empty($rows)) {
                VehicleRepairRequisitionItem::insert($rows);
            }

            return $requisition;
        });

        ActivityLog::record(
            $wasNew ? 'created' : 'updated',
            'Vehicle Repair Requisition',
            ($wasNew ? 'Filled out' : 'Updated') . ' the Vehicle Repair Requisition Slip for [' . strtoupper($maintenanceRecord->vehicle->plate_number) . '] (' . $itemCount . ' item' . ($itemCount === 1 ? '' : 's') . ').',
            $maintenanceRecord,
            ['after' => ['requisition_no' => $requisition->requisition_no, 'items' => $itemCount]]
        );

        $message = 'Vehicle Repair Requisition Slip saved.';
        if ($itemCount > 0) {
            $message .= ' This job is now ready to be marked Completed.';
        } else {
            $message .= ' Add at least one part/material before this job can be marked Completed.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
