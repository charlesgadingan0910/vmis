<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per "a driver had custody of a vehicle, from this date until
 * that date (or still-ongoing)". See the driver_assignments migration's
 * docblock for why this exists separately from vehicles.assigned_driver_id.
 */
class DriverAssignment extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'assigned_by',
        'assigned_at',
        'unassigned_by',
        'unassigned_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function unassignedByUser()
    {
        return $this->belongsTo(User::class, 'unassigned_by');
    }

    /**
     * Still-open assignments — at most one per vehicle at any given time
     * (enforced in DriverAssignmentController::assign()/unassign() and
     * VehicleController's store()/update() hooks, not at the DB level).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('unassigned_at');
    }
}
