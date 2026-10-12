<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Register → Assign Personnel → Approve step ahead of a trip —
     * deliberately NOT required for every trip. A driver's routine solo
     * errand still starts instantly from the app with no registration at
     * all (see Api\TripLogController::store()), exactly as before. This
     * table only exists for the two cases that need a paper trail before
     * GPS tracking starts: a SCHEDULED trip planned ahead of time, or any
     * trip with an assistant assigned alongside the driver (a Logistics
     * Run-style crew). An EMERGENCY trip is registered too (for the
     * record) but is auto-approved at creation — see `status`/`approved_*`
     * below — so it never blocks on anyone being online to approve it.
     *
     * Lifecycle: pending_approval → approved (or rejected) → active (the
     * driver tapped Start Trip — see Api\TripRegistrationController::start())
     * → completed (the trip was logged — see trip_log_id, set by
     * Api\TripLogController::store() once the driver taps End Trip). A
     * registration can also be cancelled before it ever starts.
     *
     * This is intentionally separate from both trip_logs (the permanent
     * record of a *completed* trip) and active_trip_sessions (the live
     * position feed for the Live Monitoring map) — a registration is the
     * paperwork that precedes a trip, not the trip itself.
     */
    public function up(): void
    {
        Schema::create('trip_registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();

            // The second crew member on a multi-personnel trip (e.g. a
            // Logistics Run) — a registered Driver profile, same as
            // driver_id, not a free-text name, so there's a real,
            // accountable record of who rode along. Null for a
            // single-driver scheduled or emergency trip.
            $table->foreignId('assistant_driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            $table->enum('trip_type', ['scheduled', 'unscheduled', 'emergency']);
            $table->string('purpose')->nullable();
            $table->string('origin', 150);
            $table->string('destination', 150);

            // Only meaningful for trip_type = scheduled — when the driver
            // plans to actually depart. Null for unscheduled/emergency,
            // which are registered right as (or just before) they start.
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_departure_time')->nullable();

            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'active', 'completed', 'cancelled'])
                ->default('pending_approval');

            $table->foreignId('registered_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();

            // Set once the driver ends the trip and the permanent record
            // is created — see Api\TripLogController::store(). Lets the
            // Trip Registrations list link straight through to the actual
            // logged trip instead of just saying "completed".
            $table->foreignId('trip_log_id')->nullable()->constrained('trip_logs')->nullOnDelete();

            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_registrations');
    }
};
