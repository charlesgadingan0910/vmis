<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VMIS Additional Updates item 3: the date a vehicle's status became
     * Unserviceable. Set/cleared server-side in VehicleController whenever
     * status changes (see normalizeUnserviceableTracking) — never left to the
     * form — so it's a reliable clock for the "still Unserviceable after 3
     * months" admin alert (Vehicle::scopeUnserviceableAlert).
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('unserviceable_since')->nullable()->after('disposal_date');
        });

        // Vehicles already on file as Unserviceable before this update existed
        // don't have a real "became Unserviceable" date to backfill, so they
        // start the 90-day clock from today rather than being left null (which
        // would exempt them from the alert entirely until their next edit).
        DB::table('vehicles')->where('status', 'UNSERVICEABLE')->update(['unserviceable_since' => now()->toDateString()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('unserviceable_since');
        });
    }
};
