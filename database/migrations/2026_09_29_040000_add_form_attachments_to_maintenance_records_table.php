<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Three official PRO5/RLRDD forms, uploaded (filled out/scanned) per
     * record alongside the existing generic receipt/invoice attachment:
     *
     * - technical_inspection_path: Technical Inspection Report — applies to
     *   both Maintenance & PMS and Repairs (MaintenanceController/
     *   RepairController both expose this field).
     * - requisition_slip_path: Vehicle Repair Requisition Slip — Repairs
     *   only, when parts need to be bought (RepairController).
     * - service_request_path: Motorpool Service Request Form — Maintenance
     *   & PMS only, for Change Oil / routine PMS (MaintenanceController).
     *
     * All three stay nullable and unused by whichever module a column
     * doesn't apply to, same live table both modules already share.
     */
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->string('technical_inspection_path')->nullable()->after('attachment_path');
            $table->string('requisition_slip_path')->nullable()->after('technical_inspection_path');
            $table->string('service_request_path')->nullable()->after('requisition_slip_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn(['technical_inspection_path', 'requisition_slip_path', 'service_request_path']);
        });
    }
};
