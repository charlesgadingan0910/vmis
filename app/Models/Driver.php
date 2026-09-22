<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'rank', 'firstname', 'middlename', 'lastname', 'qlfr', 
        'license_number', 'license_expiration_date', 'license_type', 
        'contact_number', 'status', 'photo_path'
    ];

    protected $casts = [
        'license_expiration_date' => 'date',
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