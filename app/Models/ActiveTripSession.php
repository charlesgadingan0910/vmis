<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per vehicle currently mid-trip, kept live by the driver app's
 * periodic GPS pings (see Api\ActiveTripController). Powers the admin
 * Live Monitoring map only — this is transient, in-progress state, not a
 * permanent record; see TripLog for the append-only record a trip becomes
 * once the driver taps End Trip.
 */
class ActiveTripSession extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'started_by',
        'started_at',
        'latest_latitude',
        'latest_longitude',
        'last_ping_at',
        'point_count',
        'distance_km',
        'route_points',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_ping_at' => 'datetime',
            'latest_latitude' => 'decimal:7',
            'latest_longitude' => 'decimal:7',
            'distance_km' => 'decimal:2',
            'route_points' => 'array',
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
     * Whoever's phone is actually sending the pings — always the driver's
     * own login today (see Api\ActiveTripController::resolveVehicle(),
     * which only accepts DRIVER-type accounts), kept as its own column
     * rather than reusing driver_id for the same audit-trail reasoning as
     * TripLog::loggedBy().
     */
    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * True once a session has gone quiet long enough that the admin map
     * should stop treating its pin as trustworthy — the app may have
     * crashed, lost signal, or the driver's battery died. Still shown
     * (dimmed) rather than hidden, since "last known position" is still
     * useful; only LiveMonitoringController's lazy 30-minute purge
     * actually removes a session from the map.
     */
    public function isStale(): bool
    {
        return $this->last_ping_at === null || $this->last_ping_at->lt(now()->subSeconds(90));
    }
}
