<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'units';

    protected $fillable = [
        'unit_name',
        'unit_abbvr',
    ];

    /**
     * Every station that falls under this unit.
     */
    public function stations()
    {
        return $this->hasMany(Station::class);
    }
}
