<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'or_file_path',
        'cr_file_path',
        'registration_year',
        'uploaded_by',
    ];

    /**
     * The vehicle this OR/CR registration belongs to.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The user who uploaded this registration record.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
