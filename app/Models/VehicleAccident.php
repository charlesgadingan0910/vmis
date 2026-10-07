<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single accident/incident report for a fleet vehicle. See the
 * create_vehicle_accidents_table migration's docblock for why this is its
 * own module rather than living inside Maintenance/Repairs.
 */
class VehicleAccident extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'logged_by',
        'accident_date',
        'accident_time',
        'location',
        'description',
        'severity',
        'estimated_cost',
        'police_report_no',
        'photo_path',
    ];

    public const SEVERITY_MINOR    = 'MINOR';
    public const SEVERITY_MODERATE = 'MODERATE';
    public const SEVERITY_MAJOR    = 'MAJOR';

    public const SEVERITIES = [
        self::SEVERITY_MINOR    => 'Minor',
        self::SEVERITY_MODERATE => 'Moderate',
        self::SEVERITY_MAJOR    => 'Major / Totaled',
    ];

    protected function casts(): array
    {
        return [
            'accident_date'  => 'date',
            'estimated_cost' => 'decimal:2',
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
     * Who filed this report — see the migration's docblock on why this is
     * tracked separately from driver_id.
     */
    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
