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
        'round_trip_group',
        'leg',
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

    /**
     * True once this row is one leg of a round trip (round_trip_group set) —
     * both the outbound and return legs return true, since both carry the
     * same shared group value.
     */
    public function isRoundTrip(): bool
    {
        return ! is_null($this->round_trip_group);
    }

    /**
     * The other leg of this round trip (outbound <-> return), or null for a
     * plain one-way trip, or if the partner row was somehow deleted. Not a
     * real Eloquent relation since the two rows link by a shared group value
     * rather than a foreign key — a simple lookup is clearer here than
     * contorting hasOne/belongsTo around that.
     */
    public function roundTripPartner(): ?self
    {
        if (! $this->isRoundTrip()) {
            return null;
        }

        return static::where('round_trip_group', $this->round_trip_group)
            ->where('id', '!=', $this->id)
            ->first();
    }
}
