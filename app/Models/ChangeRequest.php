<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A change held for approval (P2-01). See App\Services\ApprovalService.
 */
class ChangeRequest extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const CATEGORY_ASSIGNMENT = 'assignment';
    public const CATEGORY_DISPOSAL   = 'disposal';

    protected $fillable = [
        'type', 'category', 'vehicle_id', 'driver_id', 'summary', 'payload',
        'requested_by', 'requester_role', 'status',
        'decided_by', 'decided_at', 'decision_note', 'result_message',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
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
}
