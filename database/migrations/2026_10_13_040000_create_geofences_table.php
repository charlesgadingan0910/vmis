<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named zones drawn on the Live Monitoring map — a circle or polygon
     * that vehicles are either expected to stay OUT of (zone_type =
     * restricted, e.g. an area the fleet shouldn't enter) or expected to
     * stay WITHIN (zone_type = boundary, e.g. a city/province limit or a
     * station's own compound). zone_type only drives the default
     * alert_on_enter/alert_on_exit values and the map styling/label — the
     * actual trigger behavior is alert_on_enter/alert_on_exit themselves,
     * which an admin can override per zone (e.g. "alert on both" for a
     * sensitive area).
     *
     * geometry holds either {"center":{"lat":..,"lng":..},"radius_meters":..}
     * (shape_type = circle) or {"points":[{"lat":..,"lng":..}, ...]}
     * (shape_type = polygon) — see Geofence::containsPoint() for how each
     * is evaluated. Kept as one JSON column rather than separate
     * lat/lng/radius/points columns since only one shape's worth of data
     * is ever populated per row and a polygon's point count is unbounded.
     *
     * unit_id/station_id scope which vehicles this zone actually applies
     * to (see GeofenceDetectionService) and who can manage it — both null
     * means fleet-wide, matching Driver Assignments' convention of a null
     * scope column meaning "no restriction" rather than "no vehicles".
     */
    public function up(): void
    {
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            // restricted | boundary — see this migration's docblock.
            $table->string('zone_type')->default('restricted');

            // circle | polygon
            $table->string('shape_type');
            $table->json('geometry');

            $table->string('color', 7)->default('#ef4444');

            $table->boolean('alert_on_enter')->default(true);
            $table->boolean('alert_on_exit')->default(false);

            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['unit_id', 'station_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};
