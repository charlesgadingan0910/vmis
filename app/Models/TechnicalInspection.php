<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Structured, queryable version of the official PNP PRO5/RLRDD "Technical
 * Inspection Report". VMIS previously only let staff attach a scanned/filled
 * PDF copy of this form to a maintenance/repair record (see
 * MaintenanceController/RepairController's technical_inspection_path). That
 * satisfies the paper trail, but a PDF can't be queried — it was useless for
 * spotting which specific vehicle part keeps needing repair. This table
 * (plus TechnicalInspectionItem) captures the SAME checklist as structured
 * per-component data, one row per maintenance/repair record, so the system
 * can flag parts trending toward failure — see atRiskComponents() below,
 * which is what powers the Dashboard's "Predictive Maintenance" alert panel.
 * The PDF upload stays as-is alongside this — this is additive, not a
 * replacement for the signed/scanned copy.
 */
class TechnicalInspection extends Model
{
    use HasFactory;

    /**
     * The exact systems/components from the official Technical Inspection
     * Report form, grouped the same way the paper form groups them. This is
     * the single source of truth for both the checklist UI (accordion
     * sections + rows, built from this in the technical-inspections._modal
     * partial) and for validating which component_name/system_category pairs
     * are acceptable when saving (see TechnicalInspectionController::save()).
     */
    public const CHECKLIST = [
        'ENGINE_ASSEMBLY' => [
            'label' => 'Engine Assembly',
            'components' => [
                'Oil Filter', 'Air Filter', 'Piston assembly', 'Accelerator Cable/sensor',
                'Carburetor/Servo/EFI', 'Manifold, intake', 'Manifold exhaust', 'Fan Belt/Drive belt',
                'Idle arm', 'Engine support', 'Gasket', 'Deep stick', 'Injection pump assy',
                'Glow plug assembly', 'Engine fan', 'Turbo charger', 'Others',
            ],
        ],
        'COOLING_SYSTEM' => [
            'label' => 'Cooling System',
            'components' => [
                'Radiator assembly', 'Water pump assembly', 'Hose water inlet', 'Hose water outlet',
                'Hose bypass', 'Pipe water plug', 'Auxiliary fan', 'Coolant reservoir tank', 'Others',
            ],
        ],
        'AC_UNIT' => [
            'label' => 'Air Conditioner Unit',
            'components' => ['Compressor assembly', 'Condenser', 'Evaporator', 'Others'],
        ],
        'CHASSIS_SUSPENSION' => [
            'label' => 'Chassis and Suspension',
            'components' => [
                'Wheel front/rear', 'Wheel cylinder front', 'Suspension bushing', 'Wheel cylinder rear',
                'Tie rod end', 'Shackle bolt', 'Spring assembly front', 'Spring assembly rear',
                'Shock absorber (F)', 'Shock absorber (R)', 'Pillow block', 'Ball joint Left & Right',
                'Trunnion shaft', 'Universal Joint', 'Others',
            ],
        ],
        'TRANSMISSION' => [
            'label' => 'Transmission',
            'components' => [
                'Transmission assy', 'Clutch disc', 'Clutch pressure plate', 'Release bearing',
                'Differential assembly', 'Transfer case', 'Propeller shaft', 'Steering wheel assy',
                'Rack-end pinion assy', 'Cross joint', 'Center bearing', 'Axie front/rear assy',
                'Axie intermediate', 'Others',
            ],
        ],
        'ELECTRIC_SYSTEM' => [
            'label' => 'Electric System',
            'components' => [
                'Highlight assembly', 'Taillight assembly', 'Signal light Left & Right', 'Ignition Coil',
                'Starter motor assembly', 'Generator assembly', 'Alternator motor assy', 'Spark plug',
                'Battery terminal', 'Battery cable', 'Battery', 'Horn assembly', 'Wiring ignition',
                'Voltage regulator', 'High tension wire', 'Distributor assembly', 'Distributor cap',
                'Headlight switch', 'Panel light and indicator', 'Ignition switch', 'Blinker', 'Siren',
                'Flasher relay (hazard/signal)', 'Others',
            ],
        ],
        'FUEL_SYSTEM' => [
            'label' => 'Fuel System',
            'components' => [
                'Fuel tank', 'Fuel filter', 'Fuel pump assembly', 'Fuel injector',
                'Power pump assembly', 'Common rail sensor', 'Others',
            ],
        ],
        'INTERIOR_PARTS' => [
            'label' => 'Interior Parts',
            'components' => [
                'Seat belt', 'Seat cover', 'Gear shifts handle', 'Door handle', 'Air-con control',
                'Steering wheel', 'Dashboard panel', 'Pedals', 'Center mirror', 'Upholstery', 'Others',
            ],
        ],
        'EXTERNAL_PARTS' => [
            'label' => 'External Parts',
            'components' => [
                'Body', 'Bumper & Frame', 'Board Running', 'Bows', 'Carrier tire', 'Door Left & Right',
                'Fender Left & Right', 'Gate tail', 'Guard headlight', 'Glass windshield',
                'Windshield frame', 'Hood', 'Hood catch', 'Guard radiator', 'Hook bow', 'Pentel hook',
                'Side mirror Left & Right', 'Wiper motor & blade', 'Paint', 'Spare tire',
                'Window strip', 'Window riser', 'Others',
            ],
        ],
        'TIRES_BRAKES' => [
            'label' => 'Tires and Brake System',
            'components' => [
                'Tires and record', 'Rotor Disc', 'Master cylinder assy', 'Brake drum',
                'Wheel ream/Mags', 'Brake shoe/pad', 'Hydrovac assembly', 'Others',
            ],
        ],
    ];

    /**
     * Status legend exactly as printed on the form.
     */
    public const STATUSES = [
        'SVC'   => 'Serviceable',
        'UNSVC' => 'Unserviceable',
        'BER'   => 'Beyond Economic Repair',
        'REPR'  => 'Repairable',
        'RPLC'  => 'Replaceable',
        'NA'    => 'Not Applicable',
    ];

    /**
     * Any status other than these means the item is flagged as having *some*
     * issue — used both by the recurring-repair check below and by the
     * "flagged before" hint shown next to a component while filling out a
     * new checklist (see TechnicalInspectionController::edit()).
     */
    public const OK_STATUSES = ['SVC', 'NA'];

    /**
     * A single occurrence of one of these already means the vehicle needs
     * action now — not a trend to watch, an explicit call already made by
     * whoever inspected it — so these trigger the Dashboard alert on the very
     * next inspection, no repetition required.
     */
    public const IMMEDIATE_ALERT_STATUSES = ['BER', 'UNSVC', 'RPLC'];

    /**
     * REPR ("Repairable") means it was fixable this time — but a part that
     * keeps coming back as REPR across inspections is wearing out faster than
     * it's being replaced. This is the actual "predictive" half of the
     * feature: catching that pattern before the part degrades to BER/UNSVC.
     */
    public const RECURRING_ALERT_STATUS = 'REPR';
    public const RECURRING_ALERT_THRESHOLD = 2; // out of the last RECURRING_ALERT_WINDOW inspections
    public const RECURRING_ALERT_WINDOW = 3;

    protected $fillable = [
        'maintenance_record_id',
        'vehicle_id',
        'inspection_date',
        'inspected_by',
        'witness',
        'findings_recommendation',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
        ];
    }

    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items()
    {
        return $this->hasMany(TechnicalInspectionItem::class);
    }

    /**
     * Component-level history for ONE vehicle, scoped to the last
     * RECURRING_ALERT_WINDOW inspections per component — used to show a
     * "flagged N times before" hint on the checklist form itself while it's
     * being filled out. $excludingInspectionId leaves out the inspection
     * currently being edited, so a record doesn't count itself as history.
     *
     * Returns [component_name => ['flagged_count' => int, 'latest_status' => ?string, 'latest_date' => ?Carbon]].
     */
    public static function componentHistoryForVehicle(int $vehicleId, ?int $excludingInspectionId = null): array
    {
        $query = TechnicalInspectionItem::query()
            ->select(
                'technical_inspection_items.system_category',
                'technical_inspection_items.component_name',
                'technical_inspection_items.status',
                'technical_inspections.inspection_date',
                'technical_inspections.id as inspection_id'
            )
            ->join('technical_inspections', 'technical_inspections.id', '=', 'technical_inspection_items.technical_inspection_id')
            ->where('technical_inspections.vehicle_id', $vehicleId)
            ->whereNotNull('technical_inspection_items.status')
            ->orderByDesc('technical_inspections.inspection_date')
            ->orderByDesc('technical_inspections.id');

        if ($excludingInspectionId) {
            $query->where('technical_inspections.id', '!=', $excludingInspectionId);
        }

        // Every system on the form has its own generic "Others" row, so
        // component_name alone is NOT unique — grouping has to be keyed by
        // system_category + component_name together, or an "Others" entry
        // from Engine Assembly would get merged with one from Cooling System.
        $history = [];
        foreach ($query->get()->groupBy(fn ($r) => $r->system_category . "\x1F" . $r->component_name) as $key => $rows) {
            $recent = $rows->take(self::RECURRING_ALERT_WINDOW);
            $flaggedCount = $recent->filter(fn ($r) => ! in_array($r->status, self::OK_STATUSES, true))->count();

            $history[$key] = [
                'flagged_count' => $flaggedCount,
                'latest_status' => optional($recent->first())->status,
                'latest_date'   => optional(optional($recent->first())->inspection_date)?->format('M d, Y'),
            ];
        }

        return $history;
    }

    /**
     * Fleet-wide (or scoped to a given set of vehicle ids) list of components
     * that need proactive attention — the data behind the Dashboard's
     * "Predictive Maintenance" alert panel. See IMMEDIATE_ALERT_STATUSES /
     * RECURRING_ALERT_* above for exactly what "at risk" means. Worst-first:
     * an immediate flag (BER/UNSVC/RPLC just found) ranks above a
     * merely-recurring REPR pattern.
     */
    public static function atRiskComponents(?array $vehicleIds = null): Collection
    {
        $query = TechnicalInspectionItem::query()
            ->select(
                'technical_inspection_items.system_category',
                'technical_inspection_items.component_name',
                'technical_inspection_items.status',
                'technical_inspections.vehicle_id',
                'technical_inspections.id as inspection_id',
                'technical_inspections.inspection_date'
            )
            ->join('technical_inspections', 'technical_inspections.id', '=', 'technical_inspection_items.technical_inspection_id')
            ->whereNotNull('technical_inspection_items.status')
            ->orderByDesc('technical_inspections.inspection_date')
            ->orderByDesc('technical_inspections.id');

        if ($vehicleIds !== null) {
            $query->whereIn('technical_inspections.vehicle_id', $vehicleIds);
        }

        $atRisk = collect();

        // Same "Others" collision as componentHistoryForVehicle() above, plus
        // this is fleet-wide — so the group key needs vehicle_id AND
        // system_category AND component_name to stay unique.
        $grouped = $query->get()->groupBy(fn ($row) => $row->vehicle_id . "\x1F" . $row->system_category . "\x1F" . $row->component_name);

        foreach ($grouped as $key => $rows) {
            $recent = $rows->take(self::RECURRING_ALERT_WINDOW);
            $latest = $recent->first();
            $recurringCount = $recent->filter(fn ($r) => $r->status === self::RECURRING_ALERT_STATUS)->count();

            $isImmediate = in_array($latest->status, self::IMMEDIATE_ALERT_STATUSES, true);
            $isRecurring = $recurringCount >= self::RECURRING_ALERT_THRESHOLD;

            if (! $isImmediate && ! $isRecurring) {
                continue;
            }

            [$vehicleId, , $componentName] = explode("\x1F", $key, 3);

            $atRisk->push([
                'vehicle_id'      => (int) $vehicleId,
                'system_category' => self::CHECKLIST[$latest->system_category]['label'] ?? $latest->system_category,
                'component_name'  => $componentName,
                'latest_status'   => $latest->status,
                'reason'          => $isImmediate
                    ? (self::STATUSES[$latest->status] ?? $latest->status)
                    : "Marked Repairable in {$recurringCount} of its last {$recent->count()} inspections",
                'is_recurring'    => $isRecurring && ! $isImmediate,
                'inspection_id'   => $latest->inspection_id,
                'inspection_date' => $latest->inspection_date,
            ]);
        }

        return $atRisk->sortBy(fn ($row) => $row['is_recurring'] ? 1 : 0)->values();
    }
}
