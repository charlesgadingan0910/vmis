<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleRepairRequisitionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_repair_requisition_id',
        'qty',
        'particulars',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function requisition()
    {
        return $this->belongsTo(VehicleRepairRequisition::class, 'vehicle_repair_requisition_id');
    }
}
