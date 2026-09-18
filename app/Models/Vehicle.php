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
        'encoded_by'
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

    public function encoder() {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function registrations() {
        return $this->hasMany(VehicleRegistration::class)->latest('registration_year');
    }

    public function latestRegistration() {
        return $this->hasOne(VehicleRegistration::class)->latestOfMany('registration_year');
    }

    /**
     * Every time this vehicle's QR sticker was printed, and by whom.
     */
    public function qrPrints()
    {
        return $this->hasMany(VehicleQrPrint::class)->latest('printed_at');
    }

    public function latestQrPrint()
    {
        return $this->hasOne(VehicleQrPrint::class)->latestOfMany('printed_at');
    }
}
