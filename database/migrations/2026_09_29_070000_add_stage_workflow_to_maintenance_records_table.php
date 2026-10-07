<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VMIS process-flow redesign: a maintenance/repair record no longer gets
 * filled out completely in one step. It now moves through stages —
 * Requested (digitized Motorpool Service Request Form fields below) ->
 * Inspected (Technical Inspection Report checklist) -> Awaiting Parts
 * (Vehicle Repair Requisition Slip, only when the inspection says parts are
 * needed) -> Completed (the actual service/repair details this table
 * already had before this migration: cost, performed_by, odometer_km,
 * etc.). See App\Models\MaintenanceRecord's STAGE_* constants and
 * canComplete()/canFillRequisition() for the rules, and
 * MaintenanceController/RepairController's store()/completeService() for
 * where each stage's fields get written.
 *
 * service_date is deliberately left NOT NULL and untouched here — this
 * project has no doctrine/dbal installed, so an existing NOT NULL column
 * can't be altered to nullable without it (that package can't be installed
 * remotely either). A newly-Requested record just gets service_date set to
 * its request_date as a placeholder, overwritten with the real completion
 * date once the record reaches Completed — every piece of existing code
 * that formats service_date without a null-check keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->string('control_number')->nullable()->unique()->after('id');
            $table->string('stage', 20)->default('REQUESTED')->after('maintenance_type');
            $table->string('nature_of_request', 30)->nullable()->after('stage');
            $table->date('request_date')->nullable()->after('nature_of_request');
            $table->string('requested_by')->nullable()->after('request_date');
            $table->string('recommended_by')->nullable()->after('requested_by');
            $table->string('approved_by')->nullable()->after('recommended_by');
            $table->boolean('parts_needed')->nullable()->after('approved_by');
        });

        // Every row that already exists at this point predates the staged
        // workflow — it was logged back when one form captured the whole job
        // in a single step, so it's already effectively done. Without this,
        // the new column's own DEFAULT('REQUESTED') would leave every
        // existing record stuck behind a brand-new gate it never needed to
        // pass (and would hide it from "Serviced This Month"-style stats,
        // which now only count Completed rows).
        DB::table('maintenance_records')->update(['stage' => 'COMPLETED']);
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn([
                'control_number', 'stage', 'nature_of_request', 'request_date',
                'requested_by', 'recommended_by', 'approved_by', 'parts_needed',
            ]);
        });
    }
};
