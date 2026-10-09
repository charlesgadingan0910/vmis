<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only Maintenance & PMS / Repairs history for the VMIS mobile app.
 *
 * VIEWER accounts only — deliberately, not an oversight. Both
 * \App\Http\Controllers\MaintenanceController and
 * \App\Http\Controllers\RepairController explicitly 403 a DRIVER account on
 * the desktop ("Driver accounts do not have access to Maintenance & PMS" /
 * "...to Repairs" — a driver has no reason to be on either page, it isn't
 * part of their job). This endpoint enforces that exact same restriction
 * rather than quietly opening it up just because it's a new surface, so a
 * driver's mobile access stays identical in scope to their desktop access.
 */
class ServiceHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (strtoupper(trim((string) $request->user()->account_type)) !== 'VIEWER') {
            return response()->json([
                'success' => false,
                'message' => 'Maintenance & repair history is only available to VIEWER accounts.',
            ], 403);
        }

        $query = MaintenanceRecord::with(['vehicle', 'recorder'])
            ->orderByDesc('service_date')
            ->orderByDesc('id');

        // 'repair' narrows to the Repairs module's rows; anything else (or
        // omitted) shows Maintenance & PMS — the same partition the
        // desktop's two separate modules use, via the model's own scopes.
        if ($request->get('type') === 'repair') {
            $query->repairsOnly();
        } else {
            $query->excludingRepairs();
        }

        if ($vehicleId = $request->integer('vehicle_id')) {
            $query->where('vehicle_id', $vehicleId);
        }

        $records = $query->paginate(20);

        return response()->json([
            'success' => true,
            'current_page' => $records->currentPage(),
            'last_page' => $records->lastPage(),
            'total' => $records->total(),
            'records' => collect($records->items())->map(fn (MaintenanceRecord $r) => $this->payload($r)),
        ]);
    }

    protected function payload(MaintenanceRecord $r): array
    {
        return [
            'id' => $r->id,
            'vehicle_plate_number' => $r->vehicle ? strtoupper($r->vehicle->plate_number) : null,
            'maintenance_type' => $r->maintenance_type,
            'maintenance_type_label' => MaintenanceRecord::TYPES[$r->maintenance_type] ?? $r->maintenance_type,
            'stage' => $r->stage,
            'stage_label' => MaintenanceRecord::STAGES[$r->stage] ?? $r->stage,
            'control_number' => $r->control_number,
            'description' => $r->description,
            'service_date' => optional($r->service_date)->toDateString(),
            'request_date' => optional($r->request_date)->toDateString(),
            'odometer_km' => $r->odometer_km,
            'cost' => $r->cost !== null ? (float) $r->cost : null,
            'performed_by' => $r->performed_by,
            'next_due_date' => optional($r->next_due_date)->toDateString(),
            'next_due_odometer_km' => $r->next_due_odometer_km,
        ];
    }
}
