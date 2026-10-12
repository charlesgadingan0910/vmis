<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs the admin Live Monitoring map — a *live, in-progress* view,
     * deliberately separate from trip_logs (the permanent, append-only
     * record written once a trip actually ends). The driver app pings
     * Api\ActiveTripController::ping() roughly every 15s while GPS
     * tracking is active (see TripTrackingService on the Flutter side);
     * LiveMonitoringController reads this table to draw pins on the map.
     *
     * One row per vehicle currently mid-trip — the unique index on
     * vehicle_id means a new ping simply updates the existing row rather
     * than ever creating a second "active trip" for the same vehicle.
     * Deleted the moment the driver taps End Trip (Api\ActiveTripController::end()),
     * or lazily purged by LiveMonitoringController after 30 minutes of
     * silence if the app never explicitly ended it (crash, dead battery).
     *
     * route_points here is a *capped* recent trail (newest points only —
     * see ActiveTripController::MAX_ROUTE_POINTS), just enough for the
     * live map to draw a meaningful "where it's been recently" line
     * without the ping payload growing unbounded over a multi-hour tour.
     * The full, uncapped, authoritative route only ever lands in
     * trip_logs.route_points once the trip is actually logged.
     */
    public function up(): void
    {
        Schema::create('active_trip_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->unique()->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('started_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->decimal('latest_latitude', 10, 7)->nullable();
            $table->decimal('latest_longitude', 10, 7)->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->unsignedInteger('point_count')->default(0);
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->json('route_points')->nullable();
            $table->timestamps();

            $table->index('last_ping_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_trip_sessions');
    }
};
