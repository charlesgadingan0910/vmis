<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Real-time GPS capture for Trip Logs. A trip logged the old way (typed
     * origin/destination, manual odometer) leaves all of these null — they
     * only get filled in when the driver app actually tracked the trip live
     * (see TripTrackingService / Api\TripLogController::store()). Like the
     * round-trip columns added earlier, this is purely additive: nothing
     * here is required, so every existing row and every future manually-
     * logged trip keeps working unchanged.
     *
     * - start_latitude/start_longitude, end_latitude/end_longitude: the GPS
     *   fix captured the moment the driver tapped Start Trip / End Trip.
     * - route_points: the full breadcrumb trail recorded while the trip was
     *   in progress — a JSON array of {lat, lng, recorded_at}, ordered by
     *   time. Capped at 3,000 points per trip (validated in both
     *   TripLogController::store() methods) — comfortably more than a full
     *   duty-tour trip needs at a sane sampling interval.
     * - distance_km: total distance covered, in kilometers. Whenever
     *   route_points is present this is always server-recomputed from it
     *   (see App\Support\GeoDistance::totalKm()) rather than trusted from
     *   the app, so it can't be spoofed; it's only taken as-is from the
     *   client in the rare case a trip has start/end coordinates but no
     *   full route.
     *
     * A round trip's return leg (the second trip_logs row — see
     * add_round_trip_fields_to_trip_logs_table) is logged the old manual
     * way in this first pass and so leaves all of these columns null even
     * when its outbound leg was GPS-tracked; see the Trip Log GPS section
     * of the engagement notes for why that's an intentional v1 scope cut
     * rather than an oversight.
     */
    public function up(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->decimal('start_latitude', 10, 7)->nullable()->after('leg');
            $table->decimal('start_longitude', 10, 7)->nullable()->after('start_latitude');
            $table->decimal('end_latitude', 10, 7)->nullable()->after('start_longitude');
            $table->decimal('end_longitude', 10, 7)->nullable()->after('end_latitude');
            $table->decimal('distance_km', 8, 2)->nullable()->after('end_longitude');
            $table->json('route_points')->nullable()->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->dropColumn([
                'start_latitude',
                'start_longitude',
                'end_latitude',
                'end_longitude',
                'distance_km',
                'route_points',
            ]);
        });
    }
};
