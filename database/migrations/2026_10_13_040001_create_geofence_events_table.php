<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured, permanent log of every enter/exit transition
     * GeofenceDetectionService detects while a trip is live — the
     * geofencing equivalent of driver_assignments: fleet_alerts (see that
     * migration's docblock) is the notification INBOX (read/unread,
     * upserted/pruned for the other alert types), this table is the
     * accountability RECORD, never pruned, and exists independently of
     * whether its matching fleet_alerts row ever gets created, read, or
     * is still visible.
     *
     * geofence_name snapshots the zone's name at the moment of the event
     * so history stays meaningful even after the zone itself is edited or
     * deleted — geofence_id is nullOnDelete, not cascadeOnDelete, for the
     * same reason: the event already happened regardless of whether the
     * zone that caused it still exists.
     */
    public function up(): void
    {
        Schema::create('geofence_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('geofence_id')->nullable()->constrained('geofences')->nullOnDelete();
            $table->string('geofence_name');

            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            // enter | exit
            $table->string('event_type');

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index(['vehicle_id', 'occurred_at']);
            $table->index(['geofence_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_events');
    }
};
