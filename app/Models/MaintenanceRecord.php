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

    protected $fillable = [
        'vehicle_id',
        'maintenance_type',
        'description',
        'service_date',
        'odometer_km',
        'cost',
        'performed_by',
        'next_due_date',
        'next_due_odometer_km',
        'attachment_path',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'service_date'   => 'date',
            'next_due_date'  => 'date',
            'cost'           => 'decimal:2',
        ];
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
}
