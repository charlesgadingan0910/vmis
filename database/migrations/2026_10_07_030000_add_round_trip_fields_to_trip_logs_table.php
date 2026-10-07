<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "round trip" (Origin -> Destination -> back to Origin) is still two
     * separate append-only trip_logs rows — one per leg — rather than a
     * reshaped single-row schema, so the existing one-row-per-leg table,
     * DataTables columns and audit-trail behavior all keep working unchanged.
     * These two nullable columns are purely how the two legs recognize each
     * other: both legs of the same round trip share one round_trip_group
     * value (a UUID, generated per trip in TripLogController::store()), and
     * leg says which half a row is. A plain one-way trip leaves both null.
     */
    public function up(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->string('round_trip_group', 36)->nullable()->after('remarks');
            $table->enum('leg', ['outbound', 'return'])->nullable()->after('round_trip_group');
            $table->index('round_trip_group');
        });
    }

    public function down(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->dropIndex(['round_trip_group']);
            $table->dropColumn(['round_trip_group', 'leg']);
        });
    }
};
