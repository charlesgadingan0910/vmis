<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header row for one digitized Vehicle Repair Requisition Slip — one per
 * maintenance/repair record (unique on maintenance_record_id, same pattern
 * as technical_inspections). Filled out once the Technical Inspection has
 * flagged that parts/materials are needed (MaintenanceRecord::
 * canFillRequisition()) and now what actually gates whether that record can
 * be marked Completed (MaintenanceRecord::hasRequisitionFilled()) — the
 * plain scanned-copy upload (requisition_slip_path on maintenance_records)
 * is separate, optional supporting-document evidence attached at
 * completion, not the source of truth for the parts list.
 *
 * office_unit/driver_custodian/current_mileage default from the vehicle at
 * fill-in time (see VehicleRepairRequisitionController::edit()) but are
 * stored here as their own columns since they reflect the situation at the
 * time of the repair, not necessarily the vehicle's current assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_repair_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_record_id')->unique()->constrained('maintenance_records')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('requisition_no')->nullable();
            $table->date('requisition_date')->nullable();
            $table->string('office_unit')->nullable();
            $table->string('driver_custodian')->nullable();
            $table->unsignedInteger('current_mileage')->nullable();
            $table->text('reason_for_request')->nullable();
            $table->text('findings_diagnosis')->nullable();
            $table->string('requested_by')->nullable();
            $table->string('inspected_by')->nullable();
            $table->text('repair_conducted')->nullable();
            $table->text('mechanics_in_charge')->nullable();
            $table->timestamps();

            $table->index('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_repair_requisitions');
    }
};
