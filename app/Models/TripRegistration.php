<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The Register → Assign Personnel → Approve paperwork ahead of a trip —
 * only ever created for a SCHEDULED trip or one with an assistant
 * assigned (a Logistics Run-style crew); a routine solo errand still
 * skips this entirely and goes straight to TripLog the way it always has.
 * See the migration's own docblock for the full status lifecycle.
 */
class TripRegistration extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'assistant_driver_id',
        'trip_type',
        'purpose',
        'origin',
        'destination',
        'scheduled_date',
        'scheduled_departure_time',
        'round_trip',
        'status',
        'registered_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'trip_log_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'approved_at' => 'datetime',
            'round_trip' => 'boolean',
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

    public function assistantDriver()
    {
        return $this->belongsTo(Driver::class, 'assistant_driver_id');
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function tripLog()
    {
        return $this->belongsTo(TripLog::class);
    }

    public function isEmergency(): bool
    {
        return $this->trip_type === 'emergency';
    }

    public function hasAssistant(): bool
    {
        return ! is_null($this->assistant_driver_id);
    }

    /**
     * True when this registration was marked as a round trip at the time
     * it was created — the mobile app's "Start Trip" flow for this
     * registration pre-checks/locks its own round-trip toggle to match
     * (see Api\TripRegistrationController::payload() and
     * TripsScreen's _GpsTripEndingForm), so the two stay consistent.
     */
    public function isRoundTrip(): bool
    {
        return (bool) $this->round_trip;
    }

    /**
     * True once a driver (the primary driver or the assigned assistant)
     * is allowed to tap Start Trip on this registration — approved, and
     * not already started/closed out some other way.
     */
    public function isStartable(): bool
    {
        return $this->status === 'approved';
    }
}
