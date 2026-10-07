<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header row for one digitized Technical Inspection Report — one per
 * maintenance/repair record that has a checklist filled in (unique on
 * maintenance_record_id: a record can have at most one). See
 * create_technical_inspection_items_table for the actual per-component
 * checklist data, and App\Models\TechnicalInspection for why this exists
 * alongside the plain PDF upload (technical_inspection_path on
 * maintenance_records).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_record_id')->unique()->constrained('maintenance_records')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('inspection_date')->nullable();
            $table->string('inspected_by')->nullable();
            $table->string('witness')->nullable();
            $table->text('findings_recommendation')->nullable();
            $table->timestamps();

            $table->index('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_inspections');
    }
};
