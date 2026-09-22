<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single trip entry for a vehicle. Permanent/append-only by design — see
 * TripLogController, which exposes create + view only, no update or delete,
 * the same audit-trail treatment already given to ActivityLog.
 */
class TripLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'logged_by',
        'trip_date',
        'departure_time',
        'arrival_time',
        'origin',
        'destination',
        'purpose',
        'odometer_start',
        'odometer_end',
        'passengers',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
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

    /**
     * Who actually submitted this entry — the driver themselves in almost
     * every case, or a SUPER ADMINISTRATOR logging on a driver's behalf.
     */
    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
