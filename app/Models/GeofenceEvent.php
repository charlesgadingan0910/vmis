<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per enter/exit transition GeofenceDetectionService detects —
 * see the geofence_events migration's docblock for why this is a
 * separate, permanent log rather than living in fleet_alerts.
 */
class GeofenceEvent extends Model
{
    protected $fillable = [
        'geofence_id',
        'geofence_name',
        'vehicle_id',
        'driver_id',
        'event_type',
        'latitude',
        'longitude',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'latitude'    => 'float',
            'longitude'   => 'float',
        ];
    }

    public function geofence()
    {
        return $this->belongsTo(Geofence::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
