<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The digitized Vehicle Repair Requisition Slip — Step 3 of the process
 * flow (Requested -> Inspected -> Awaiting Parts -> Completed), filled out
 * once the Technical Inspection has flagged that parts/materials are
 * needed. One per maintenance/repair record (see the migration's unique
 * constraint), same shape as App\Models\TechnicalInspection alongside its
 * own maintenance_records row. See VehicleRepairRequisitionController for
 * the AJAX endpoints and MaintenanceRecord::hasRequisitionFilled() for how
 * this now gates whether a record can be marked Completed.
 */
class VehicleRepairRequisition extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_record_id',
        'vehicle_id',
        'requisition_no',
        'requisition_date',
        'office_unit',
        'driver_custodian',
        'current_mileage',
        'reason_for_request',
        'findings_diagnosis',
        'requested_by',
        'inspected_by',
        'repair_conducted',
        'mechanics_in_charge',
    ];

    protected function casts(): array
    {
        return [
            'requisition_date' => 'date',
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

    /**
     * QTY / PARTICULARS / PRICE line items — see the items table migration
     * for why price isn't qty × a separate unit price.
     */
    public function items()
    {
        return $this->hasMany(VehicleRepairRequisitionItem::class);
    }

    /**
     * Mirrors the paper form's own TOTAL row: a plain sum of every line's
     * PRICE, not qty-weighted.
     */
    public function totalPrice(): float
    {
        return (float) $this->items->sum('price');
    }
}
