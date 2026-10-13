<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors the mobile app's manual-entry round-trip toggle
     * (_AddTripInlineForm) onto the admin Register Trip paperwork, so an
     * admin can mark a registration as a round trip up front. The actual
     * two-leg trip_logs rows are still created the same way they always
     * have been (TripLogController::store() / Api\TripLogController::store(),
     * sharing a round_trip_group) — this column is only the registration's
     * own record of intent, read back by the mobile "Start Trip" flow to
     * pre-check/lock its own round-trip toggle for a registered trip.
     */
    public function up(): void
    {
        Schema::table('trip_registrations', function (Blueprint $table) {
            $table->boolean('round_trip')->default(false)->after('scheduled_departure_time');
        });
    }

    public function down(): void
    {
        Schema::table('trip_registrations', function (Blueprint $table) {
            $table->dropColumn('round_trip');
        });
    }
};
