<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mileage-based PMS trigger — the odometer-reading counterpart to
     * next_pms_date, which vehicles already has. A vehicle is flagged
     * due/overdue for PMS once EITHER next_pms_date OR this column says
     * so (see Vehicle::pmsStatus()), whichever comes first, exactly the
     * way a manufacturer's own service schedule works ("every 6 months or
     * 5,000 km, whichever comes first").
     *
     * Kept in lockstep with next_pms_date: MaintenanceController's and
     * RepairController's syncVehicleFromLatestRecord() re-derive this from
     * the latest COMPLETED maintenance_records row's next_due_odometer_km
     * column (which already existed — the admin-facing Log/Edit/Complete
     * Service forms already capture it; it just wasn't synced to the
     * vehicle or used to trigger anything until now).
     *
     * vehicles.odometer_km itself — the "current mileage" this gets
     * compared against — already existed too, but used to only move
     * forward from a completed service's own reading. It now also moves
     * forward from every logged trip's ending odometer reading (see
     * Vehicle::bumpOdometer(), called from both TripLogController's and
     * Api\TripLogController's store()), so it keeps climbing day to day
     * instead of sitting frozen between services.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedInteger('next_pms_odometer_km')->nullable()->after('next_pms_date');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('next_pms_odometer_km');
        });
    }
};
