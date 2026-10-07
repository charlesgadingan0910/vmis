<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per checklist component (see App\Models\TechnicalInspection::CHECKLIST)
 * that was actually filled in for a given inspection. Powers
 * TechnicalInspection::atRiskComponents()/componentHistoryForVehicle(), both
 * of which look up "every past status for vehicle X + component Y" — hence
 * the composite index below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technical_inspection_id')->constrained('technical_inspections')->cascadeOnDelete();
            $table->string('system_category');
            $table->string('component_name');
            $table->string('status', 10)->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            // Named explicitly — the auto-generated name (table + both column
            // names + "_index") comes out to 73 characters, over MySQL's
            // 64-character identifier limit, which is exactly what broke this
            // migration the first time it ran.
            $table->index(['technical_inspection_id', 'component_name'], 'ti_items_inspection_component_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_inspection_items');
    }
};
