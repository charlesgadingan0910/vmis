<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'rank', 'firstname', 'middlename', 'lastname', 'qlfr', 
        'license_number', 'license_expiration_date', 'license_type', 
        'contact_number', 'status'
    ];

    protected $casts = [
        'license_expiration_date' => 'date',
    ];
}