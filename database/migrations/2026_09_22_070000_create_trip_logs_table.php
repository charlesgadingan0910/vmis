<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trip Logs: a permanent, append-only record of each vehicle's trip
     * history (like the system's Activity Log audit trail — created and
     * viewed, never edited or deleted). See TripLogController for who may
     * create/view which rows.
     */
    public function up(): void
    {
        Schema::create('trip_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            // The vehicle's assigned driver at the time the trip was logged —
            // nullable because a SUPER ADMINISTRATOR may log a trip for a
            // vehicle that currently has no driver assigned.
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            // Who actually submitted this entry (normally the driver's own
            // login account, but a SUPER ADMINISTRATOR may log on a driver's
            // behalf) — tracked separately from driver_id for an accurate
            // audit trail regardless of who the trip was "for".
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();

            $table->date('trip_date');
            $table->time('departure_time')->nullable();
            $table->time('arrival_time')->nullable();
            $table->string('origin');
            $table->string('destination');
            $table->string('purpose')->nullable();
            $table->unsignedInteger('odometer_start')->nullable();
            $table->unsignedInteger('odometer_end')->nullable();
            $table->string('passengers')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            // Every visibility/scoping query in TripLogController filters on
            // these — see scopeToVisibleVehicles()/index().
            $table->index(['vehicle_id', 'trip_date']);
            $table->index('driver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_logs');
    }
};
