<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    /**
     * DL (Driver's License) Codes — the LTO vehicle-class authorizations
     * printed on the back of the license card. Distinct from `license_type`
     * (Professional/Non-Professional), which is a separate classification.
     */
    public const DL_CODES = [
        'A'  => 'Motorcycle / motorized tricycle',
        'A1' => 'Tricycle below 400kg vehicle weight',
        'B'  => 'Up to 5,000kg GVW, max 8 seats excl. driver (car, SUV, pick-up)',
        'B1' => 'Up to 5,000kg GVW, more than 8 seats excl. driver (AUV, van)',
        'B2' => 'Up to 5,000kg GVW, used for goods transport (truck, delivery van)',
        'C'  => 'Above 5,000kg GVW (truck, bus)',
        'D'  => 'Above 5,000kg GVW, public transport, more than 8 seats (bus)',
        'BE' => 'Category B vehicle with trailer exceeding 650kg GVW',
        'CE' => 'Category C vehicle with trailer exceeding 650kg GVW',
    ];

    /**
     * Numbered driving-condition restrictions printed on the license back.
     */
    public const RESTRICTION_CODES = [
        '1' => 'Corrective lenses',
        '2' => 'Special equipment for limbs',
        '3' => 'Customized vehicle only',
        '4' => 'Daylight driving only',
        '5' => 'Hearing aid required',
    ];

    protected $fillable = [
        'rank', 'firstname', 'middlename', 'lastname', 'qlfr',
        'license_number', 'license_expiration_date', 'license_type',
        'dl_codes', 'restriction_codes', 'license_serial_number',
        'contact_number', 'emergency_contact_name', 'emergency_contact_address',
        'emergency_contact_number', 'status', 'photo_path'
    ];

    protected $casts = [
        'license_expiration_date' => 'date',
        'dl_codes' => 'array',
        'restriction_codes' => 'array',
    ];

    /**
     * The DRIVER-type login account linked to this profile, if one has been
     * created for them on the System Users page — most drivers on file may
     * not have a login account at all, so this is frequently null.
     */
    public function user()
    {
        return $this->hasOne(User::class, 'driver_id');
    }

    /**
     * Vehicle(s) currently assigned to this driver (vehicles.assigned_driver_id).
     */
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'assigned_driver_id');
    }
}