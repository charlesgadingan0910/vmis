<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Speed is only ever known for a GPS-tracked trip (TripTrackingService
     * samples Position.speed live on the device) — a manually-logged trip
     * leaves both of these null, same as distance_km/route_points already
     * do. Following the round-trip precedent in Api\TripLogController::
     * store(), these are stored only on the primary/outbound leg's row,
     * never duplicated onto an auto-created return leg.
     */
    public function up(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->decimal('avg_speed_kmh', 6, 2)->nullable()->after('distance_km');
            $table->decimal('max_speed_kmh', 6, 2)->nullable()->after('avg_speed_kmh');
        });
    }

    public function down(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->dropColumn(['avg_speed_kmh', 'max_speed_kmh']);
        });
    }
};
