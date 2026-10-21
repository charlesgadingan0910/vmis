<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MaintenanceRecord;
use App\Models\TechnicalInspection;
use App\Models\Vehicle;
use Illuminate\Http\Request;

/**
 * Printable versions of the forms VMIS already digitizes, plus a one-page
 * vehicle data card. Pure read-only output: nothing here writes to the
 * records being printed (only an Activity Log entry that a print happened).
 *
 * The layouts are VMIS's own clean A4 layouts built from the fields the
 * system stores, NOT pixel copies of the paper PNP forms. If the paper
 * layouts are supplied later, only the Blade views under resources/views/forms
 * need to change.
 *
 * Who can print: every account type except DRIVER, limited to vehicles they
 * can already see — SUPER ADMINISTRATOR, ADMINISTRATOR and VIEWER see the
 * fleet, UNIT ADMINISTRATOR their own unit, STATION ADMINISTRATOR their own
 * station (same rule as Technical Inspections and Vehicle Inventory).
 */
class PrintFormController extends Controller
{
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    /**
     * Forms that can be requested individually via ?form=; "all" prints the
     * service request plus whichever of the others actually has data.
     */
    public const FORMS = ['all', 'service-request', 'inspection', 'requisition'];

    protected function authorizeVehicle(Vehicle $vehicle): void
    {
        $user = auth()->user();
        $role = strtoupper(trim((string) $user->account_type));

        $allowed = match (true) {
            in_array($role, self::BROAD_VISIBILITY_ROLES, true) => true,
            $role === self::ROLE_UNIT_ADMIN                     => $vehicle->unit_id == $user->unit_id,
            $role === self::ROLE_STATION_ADMIN                  => $vehicle->station_id == $user->station_id,
            default                                             => false,
        };

        if (! $allowed) {
            abort(403, 'Unauthorized Action');
        }
    }

    public function maintenance(Request $request, MaintenanceRecord $maintenanceRecord)
    {
        $vehicle = $maintenanceRecord->vehicle()->with(['type', 'unit', 'station', 'driver'])->firstOrFail();
        $this->authorizeVehicle($vehicle);

        $form = (string) $request->query('form', 'all');
        if (! in_array($form, self::FORMS, true)) {
            abort(404);
        }

        $inspection = TechnicalInspection::with('items')
            ->where('maintenance_record_id', $maintenanceRecord->id)
            ->first();
        $requisition = $maintenanceRecord->requisition()->with('items')->first();

        // Asking for one form by name always prints it (blank fields are fine,
        // useful for filling in by hand); "all" only adds forms with data.
        $show = [
            'service-request' => in_array($form, ['all', 'service-request'], true),
            'inspection'      => $form === 'inspection' || ($form === 'all' && $inspection !== null),
            'requisition'     => $form === 'requisition' || ($form === 'all' && $requisition !== null),
        ];

        $inspectionItems = [];
        if ($inspection) {
            foreach ($inspection->items as $item) {
                $inspectionItems[$item->system_category . "\x1F" . $item->component_name] = $item;
            }
        }

        ActivityLog::record(
            'printed',
            'Print Forms',
            'Printed ' . ($form === 'all' ? 'the forms packet' : 'the ' . str_replace('-', ' ', $form) . ' form') . ' for [' . strtoupper($vehicle->plate_number) . '] (' . ($maintenanceRecord->control_number ?: 'record #' . $maintenanceRecord->id) . ').',
            $maintenanceRecord
        );

        return view('forms.maintenance', [
            'record'          => $maintenanceRecord,
            'vehicle'         => $vehicle,
            'inspection'      => $inspection,
            'inspectionItems' => $inspectionItems,
            'requisition'     => $requisition,
            'show'            => $show,
            'checklist'       => TechnicalInspection::CHECKLIST,
            'statusLegend'    => TechnicalInspection::STATUSES,
            'natureOptions'   => MaintenanceRecord::NATURE_OF_REQUEST,
            'typeLabels'      => MaintenanceRecord::TYPES,
            'stageLabels'     => MaintenanceRecord::STAGES,
        ]);
    }

    public function vehicleCard(Vehicle $vehicle)
    {
        $this->authorizeVehicle($vehicle);

        $vehicle->load(['type', 'unit', 'station', 'driver', 'driver2', 'latestRegistration']);

        $services = MaintenanceRecord::query()
            ->where('vehicle_id', $vehicle->id)
            ->completedOnly()
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        ActivityLog::record(
            'printed',
            'Print Forms',
            'Printed the vehicle data card for [' . strtoupper($vehicle->plate_number) . '].',
            $vehicle
        );

        return view('forms.vehicle-card', [
            'vehicle'    => $vehicle,
            'services'   => $services,
            'typeLabels' => MaintenanceRecord::TYPES,
        ]);
    }
}
