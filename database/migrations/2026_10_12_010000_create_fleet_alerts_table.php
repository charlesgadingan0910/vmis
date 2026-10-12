<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * In-app Notification Center backing store: a unified table for every
     * alert VMIS already computes elsewhere (Vehicle::needsRegistrationAlert(),
     * needsPmsAlert(), needsUnserviceableAlert(), and driver license expiry),
     * so admins and drivers see them as an actual inbox instead of only on
     * the Dashboard/Vehicle tab banners. See FleetAlertSyncService for how
     * rows here get created/refreshed/removed.
     *
     * Deliberately named `fleet_alerts` / FleetAlert, NOT `notifications` /
     * Notification — User already `use`s Illuminate\Notifications\Notifiable,
     * whose own notifications() relation expects a `notifications` table
     * shaped like Illuminate\Notifications\DatabaseNotification (uuid id,
     * notifiable_type/notifiable_id, json data). Reusing that table name
     * with a different schema would silently break the first thing that
     * ever calls Laravel's own $user->notifications.
     *
     * Each row is either vehicle-scoped (registration_due, pms_due,
     * unserviceable_alert — visible to whichever admins/drivers can already
     * see that vehicle, same role/unit/station rules as everywhere else) or
     * driver-scoped (license_expiry — visible only to that driver's own
     * linked login). Exactly one of vehicle_id/driver_id is set per row.
     *
     * `dedupe_key` is the upsert target: FleetAlertSyncService::sync() does
     * an updateOrCreate() per currently-alerting record keyed on this, so
     * re-running it never duplicates a row, refreshes the day-count in
     * title/body without resetting read_at, and a condition clearing (e.g.
     * the registration gets renewed) removes the row once its key stops
     * being regenerated.
     *
     * `read_at` is a single shared flag, not per-viewer — a known V1
     * trade-off: the first admin who opens the bell marks it read for every
     * admin who can see that same vehicle. Acceptable for now since most
     * roles here are scoped to one unit/station anyway; a per-user read
     * pivot table is the natural follow-up if that turns out to matter.
     */
    public function up(): void
    {
        Schema::create('fleet_alerts', function (Blueprint $table) {
            $table->id();

            // registration_due | pms_due | unserviceable_alert | license_expiry
            $table->string('type');

            // overdue | due_soon
            $table->string('severity');

            $table->string('title');
            $table->text('body');

            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->cascadeOnDelete();

            $table->string('dedupe_key')->unique();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index('type');
            $table->index('vehicle_id');
            $table->index('driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_alerts');
    }
};
