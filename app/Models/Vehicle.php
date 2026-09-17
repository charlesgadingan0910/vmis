<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'plate_number',
        'engine_number',
        'chassis_number',
        'make',
        'model',
        'type',
        'vehicle_type_id',
        'year_model',
        'color',
        'acquisition_date',
        'unit_id',
        'station_id',
        'assigned_driver_id',
        'odometer_km',
        'next_pms_date',
        'status',
        'is_active',
        'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'next_pms_date' => 'date',
        ];
    }

    /**
     * The driver currently designated to this vehicle.
     */
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    /**
     * The category or classification type of the vehicle.
     */
    public function type()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }
}