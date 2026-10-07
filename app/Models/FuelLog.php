<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single refueling event for a vehicle. Permanent/append-only by design —
 * see FuelLogController, which exposes create + view only, no update or
 * delete, the same audit-trail treatment already given to TripLog/ActivityLog.
 */
class FuelLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'logged_by',
        'refuel_date',
        'liters',
        'total_cost',
        'odometer_reading',
        'receipt_path',
    ];

    protected function casts(): array
    {
        return [
            'refuel_date' => 'date',
            'liters'      => 'decimal:2',
            'total_cost'  => 'decimal:2',
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
     * The same vehicle's previous refuel entry (by refuel_date, then id as
     * the tiebreaker for same-day entries) — the other half of every
     * distance/efficiency calculation below. Null for a vehicle's very
     * first logged refuel.
     */
    public function previousLog(): ?self
    {
        return static::where('vehicle_id', $this->vehicle_id)
            ->where(function ($q) {
                $q->where('refuel_date', '<', $this->refuel_date)
                  ->orWhere(function ($q2) {
                      $q2->where('refuel_date', $this->refuel_date)->where('id', '<', $this->id);
                  });
            })
            ->orderByDesc('refuel_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Odometer distance covered since the previous refuel, or null if
     * there's no previous entry or the odometer didn't actually increase
     * (a misread/out-of-order entry — safer to show nothing than a bogus
     * negative distance).
     */
    public function distanceSinceLastRefuel(): ?int
    {
        $previous = $this->previousLog();
        if (! $previous || $this->odometer_reading <= $previous->odometer_reading) {
            return null;
        }

        return $this->odometer_reading - $previous->odometer_reading;
    }

    /**
     * Fuel efficiency since the last refuel, in kilometers per liter —
     * distance covered divided by how many liters THIS fill-up added,
     * since that's the fuel that was actually consumed over that distance.
     * Null whenever distance can't be computed, or liters is zero.
     */
    public function kmPerLiter(): ?float
    {
        $distance = $this->distanceSinceLastRefuel();
        if ($distance === null || (float) $this->liters <= 0) {
            return null;
        }

        return round($distance / (float) $this->liters, 2);
    }

    /**
     * Average price paid per liter on this entry — simple derived figure,
     * not stored, so it's never at risk of drifting from the two values it
     * comes from.
     */
    public function pricePerLiter(): ?float
    {
        if ((float) $this->liters <= 0) {
            return null;
        }

        return round((float) $this->total_cost / (float) $this->liters, 2);
    }
}
