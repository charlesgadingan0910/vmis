<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A request to use a vehicle for a period (P2-02). See App\Services\DispatchService.
 */
class VehicleRequest extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'vehicle_id', 'driver_id', 'start_at', 'end_at', 'purpose', 'origin', 'destination', 'passengers',
        'requested_by', 'requester_role', 'status', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'start_at'   => 'datetime',
            'end_at'     => 'datetime',
            'decided_at' => 'datetime',
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

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Requests whose period overlaps [$start, $end) — back-to-back bookings do not overlap. */
    public function scopeOverlapping($query, $start, $end)
    {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
