<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    use HasFactory;

    /**
     * Maintenance type => human-readable label. Shared by the controller (validation,
     * badge coloring) and the view (filter/select options) so both stay in lockstep.
     */
    public const TYPES = [
        'PMS'         => 'Preventive Maintenance (PMS)',
        'REPAIR'      => 'Repair',
        'OIL_CHANGE'  => 'Oil Change',
        'TIRE_CHANGE' => 'Tire Change',
        'BATTERY'     => 'Battery Service',
        'EMERGENCY'   => 'Emergency Repair',
        'OTHER'       => 'Other',
    ];

    /**
     * Badge color keyed to the same type — kept next to TYPES rather than computed
     * client-side so the color mapping lives in exactly one place.
     */
    public const TYPE_COLORS = [
        'PMS'         => 'primary',
        'REPAIR'      => 'warning',
        'OIL_CHANGE'  => 'success',
        'TIRE_CHANGE' => 'info',
        'BATTERY'     => 'secondary',
        'EMERGENCY'   => 'danger',
        'OTHER'       => 'light',
    ];

    /**
     * The staged process flow: a request comes in (Requested, the digitized
     * Motorpool Service Request Form fields below), gets diagnosed
     * (Inspected, the Technical Inspection Report checklist), waits on parts
     * if the inspection says so (Awaiting Parts, the Vehicle Repair
     * Requisition Slip), and only then gets marked Completed (the actual
     * service/repair details — cost, performed_by, odometer_km, etc.). See
     * canComplete()/canFillRequisition() below for exactly what unlocks each
     * step, and MaintenanceController/RepairController for where they're
     * enforced. Every record that existed before this workflow was added is
     * backfilled to Completed (see the migration) so old data isn't stuck
     * behind a gate it never needed to pass.
     */
    public const STAGE_REQUESTED      = 'REQUESTED';
    public const STAGE_INSPECTED      = 'INSPECTED';
    public const STAGE_AWAITING_PARTS = 'AWAITING_PARTS';
    public const STAGE_COMPLETED      = 'COMPLETED';

    public const STAGES = [
        self::STAGE_REQUESTED      => 'Requested',
        self::STAGE_INSPECTED      => 'Inspected',
        self::STAGE_AWAITING_PARTS => 'Awaiting Parts',
        self::STAGE_COMPLETED      => 'Completed',
    ];

    public const STAGE_COLORS = [
        self::STAGE_REQUESTED      => 'secondary',
        self::STAGE_INSPECTED      => 'info',
        self::STAGE_AWAITING_PARTS => 'warning',
        self::STAGE_COMPLETED      => 'success',
    ];

    /**
     * The Motorpool Service Request Form's own checkboxes, generalized to
     * also cover Repairs (that paper form's own subtitle is "For Change Oil
     * and Routine Maintenance," but the process flow now starts every
     * request — maintenance or repair alike — the same way). Derived
     * automatically from maintenance_type at request time (see
     * NATURE_OF_REQUEST_BY_TYPE) rather than asked as a separate field, since
     * it would otherwise just duplicate that choice.
     */
    public const NATURE_OF_REQUEST = [
        'STANDARD_CHANGE_OIL' => 'Standard Change Oil',
        'FULL_PMS'            => 'Full PMS',
        'FLUID_TOP_UP'        => 'Fluid Top-Up & Check',
        'REPAIR_OTHER'        => 'Repair / Other Concern',
    ];

    public const NATURE_OF_REQUEST_BY_TYPE = [
        'OIL_CHANGE'  => 'STANDARD_CHANGE_OIL',
        'PMS'         => 'FULL_PMS',
        'TIRE_CHANGE' => 'REPAIR_OTHER',
        'BATTERY'     => 'REPAIR_OTHER',
        'EMERGENCY'   => 'REPAIR_OTHER',
        'OTHER'       => 'REPAIR_OTHER',
        'REPAIR'      => 'REPAIR_OTHER',
    ];

    protected $fillable = [
        'vehicle_id',
        'maintenance_type',
        'description',
        'service_date',
        'odometer_km',
        'cost',
        'performed_by',
        // Part IV "Certification of Completion" on the Motorpool Service
        // Request Form — see the add_received_by migration.
        'received_by',
        'next_due_date',
        'next_due_odometer_km',
        'attachment_path',
        // Official PRO5/RLRDD forms uploaded per record — see the
        // add_form_attachments_to_maintenance_records_table migration for
        // which module (Maintenance & PMS vs. Repairs) uses which.
        'technical_inspection_path',
        'requisition_slip_path',
        'service_request_path',
        'recorded_by',
        // Staged process flow — see add_stage_workflow_to_maintenance_records_table.
        'control_number',
        'stage',
        'nature_of_request',
        'request_date',
        'requested_by',
        'recommended_by',
        'approved_by',
        'parts_needed',
    ];

    protected function casts(): array
    {
        return [
            'service_date'   => 'date',
            'next_due_date'  => 'date',
            'request_date'   => 'date',
            'cost'           => 'decimal:2',
            'parts_needed'   => 'boolean',
        ];
    }

    /**
     * VMIS Additional Updates item 4: Repairs got split into its own module
     * (RepairController/repairs.index) — same table, just partitioned by
     * maintenance_type so no migration was needed. These two scopes are how
     * each controller stays out of the other's rows: MaintenanceController
     * excludes REPAIR, RepairController shows only REPAIR.
     */
    public function scopeRepairsOnly($query)
    {
        return $query->where('maintenance_type', 'REPAIR');
    }

    public function scopeExcludingRepairs($query)
    {
        return $query->where('maintenance_type', '!=', 'REPAIR');
    }

    /**
     * Only Completed records represent a vehicle's actual service history —
     * a Requested/Inspected/Awaiting Parts row's service_date is just a
     * request_date placeholder (see the stage-workflow migration), so
     * anything computing "when was this vehicle last serviced" or "cost this
     * month" has to filter to this scope, not just order by service_date.
     */
    public function scopeCompletedOnly($query)
    {
        return $query->where('stage', self::STAGE_COMPLETED);
    }

    /**
     * Whether the Vehicle Repair Requisition Slip can be filled in yet — only
     * once the Technical Inspection has flagged that parts/materials are
     * needed. Stays true even after the record reaches Completed, so the
     * paperwork can still be corrected/replaced afterward.
     */
    public function canFillRequisition(): bool
    {
        return (bool) $this->parts_needed;
    }

    /**
     * Whether the digitized Vehicle Repair Requisition Slip has actually
     * been filled out (a header row exists and it has at least one
     * particular on it) — this, not the optional scanned-copy upload
     * (requisition_slip_path), is what canComplete() below now gates on.
     * The scanned copy is just supporting evidence attached alongside the
     * Part IV certification at completion time; the digitized parts list is
     * the real record.
     */
    public function hasRequisitionFilled(): bool
    {
        $requisition = $this->requisition;

        return (bool) ($requisition && $requisition->items()->exists());
    }

    /**
     * Whether this record is allowed to move to Completed yet: it has to have
     * been inspected first, and if that inspection flagged parts as needed,
     * the digitized Requisition Slip has to actually be filled out before
     * the job can be called done.
     */
    public function canComplete(): bool
    {
        if ($this->stage === self::STAGE_REQUESTED) {
            return false;
        }
        if ($this->parts_needed && ! $this->hasRequisitionFilled()) {
            return false;
        }
        return true;
    }

    /**
     * Human-readable reason canComplete() is false, for the "Complete
     * Service" button's disabled-state tooltip — kept in one place so the UI
     * text can't drift from the actual rule above.
     */
    public function blockedCompletionReason(): ?string
    {
        if ($this->stage === self::STAGE_REQUESTED) {
            return 'Fill out the Technical Inspection first.';
        }
        if ($this->parts_needed && ! $this->hasRequisitionFilled()) {
            return 'The inspection flagged parts as needed — fill out the Requisition Slip first.';
        }
        return null;
    }

    /**
     * The vehicle this maintenance activity was performed on.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The user who logged this record.
     */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The digitized Vehicle Repair Requisition Slip for this record, if one
     * has been started — see VehicleRepairRequisition's own docblock.
     */
    public function requisition()
    {
        return $this->hasOne(VehicleRepairRequisition::class);
    }
}
