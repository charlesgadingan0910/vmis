<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Full history of which driver was the custodian of which vehicle, and
     * when. vehicles.assigned_driver_id only ever holds the CURRENT
     * assignment (if any) — without this table there was no way to answer
     * "who had this vehicle on <date>" or "when did this driver last get
     * reassigned", both of which matter for a government fleet's
     * accountability trail. A row with unassigned_at = null is that
     * vehicle's currently active assignment; every other row is closed
     * history.
     *
     * Written from two places that both ultimately change the same
     * vehicles.assigned_driver_id column: DriverAssignmentController (the
     * dedicated Driver Assignments page) and VehicleController::store()/
     * update() (the Assigned Driver field on the Vehicle Inventory form).
     * Both write a row here, so this table stays the single source of
     * truth for assignment history regardless of which screen was used.
     */
    public function up(): void
    {
        Schema::create('driver_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');

            // Both null while this is the vehicle's current, still-active assignment.
            $table->foreignId('unassigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unassigned_at')->nullable();

            $table->string('notes')->nullable();

            $table->timestamps();

            // Fast "what's this vehicle's current assignment" / "is this
            // driver currently assigned anywhere" lookups (unassigned_at IS NULL),
            // and fast per-vehicle / per-driver history listings.
            $table->index(['vehicle_id', 'unassigned_at']);
            $table->index(['driver_id', 'unassigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_assignments');
    }
};
