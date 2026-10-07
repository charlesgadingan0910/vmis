<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of a TechnicalInspection's checklist — one per (system_category,
 * component_name) pair from TechnicalInspection::CHECKLIST that was actually
 * filled in (blank rows aren't persisted — see
 * TechnicalInspectionController::save()). See TechnicalInspection for why
 * this structured data exists alongside the plain PDF upload.
 */
class TechnicalInspectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'technical_inspection_id',
        'system_category',
        'component_name',
        'status',
        'remarks',
    ];

    public function inspection()
    {
        return $this->belongsTo(TechnicalInspection::class, 'technical_inspection_id');
    }
}
