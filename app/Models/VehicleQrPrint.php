<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleQrPrint extends Model
{
    protected $fillable = [
        'vehicle_id',
        'printed_by',
        'context',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
        ];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function printer()
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
