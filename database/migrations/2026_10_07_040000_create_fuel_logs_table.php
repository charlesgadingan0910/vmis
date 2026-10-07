<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fuel Monitoring: a permanent, append-only record of every refueling
     * event — same audit-trail treatment as Trip Logs/Activity Log, and
     * deliberately its own table rather than extra columns on trip_logs.
     * A refuel doesn't line up one-to-one with a trip (a vehicle may run
     * several trips between fill-ups, or get refueled on a day with no trip
     * logged at all), and fuel efficiency is computed by comparing
     * odometer_reading between *consecutive refuels* for the same vehicle
     * (see FuelLog::distanceSinceLastRefuel()/kmPerLiter()), not between
     * trip legs — so this needs its own date+odometer-stamped timeline.
     */
    public function up(): void
    {
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            // The vehicle's assigned driver at the time of refueling —
            // nullable because a SUPER ADMINISTRATOR may log a refuel for a
            // vehicle that currently has no driver assigned. Same pattern as
            // trip_logs.driver_id.
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            // Who actually submitted this entry — the driver's own login in
            // almost every case, or a SUPER ADMINISTRATOR logging on a
            // driver's behalf. Tracked separately from driver_id, same
            // reasoning as trip_logs.logged_by.
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();

            $table->date('refuel_date');
            $table->decimal('liters', 8, 2);
            $table->decimal('total_cost', 10, 2);

            // The odometer reading AT this refuel — the one figure this
            // table needs to compute distance/efficiency between fill-ups;
            // unlike trip_logs there's no separate start/end, since a refuel
            // is a single point in time, not a leg of travel.
            $table->unsignedInteger('odometer_reading');

            // Scanned/photographed receipt, stored the same way vehicle_docs/
            // maintenance_records/etc. already do (public disk, served back
            // through a path-guarded controller route rather than relying on
            // the public/storage symlink, which is unreliable on Windows/WAMP).
            $table->string('receipt_path')->nullable();

            $table->timestamps();

            // Same indexing convention as trip_logs — every visibility/scoping
            // query and the per-vehicle "previous refuel" lookup filters on these.
            $table->index(['vehicle_id', 'refuel_date']);
            $table->index('driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};
