<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    protected $table = 'stations';

    protected $fillable = [
        'unit_id',
        'station_name',
        'station_abbvr',
    ];

    /**
     * The unit this station belongs to.
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
