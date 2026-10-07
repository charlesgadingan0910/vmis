<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vehicle Accident Records: a log of collisions/incidents involving a
     * fleet vehicle, kept alongside (not inside) Maintenance/Repairs since an
     * accident is an event report first — any resulting repair is still
     * logged as its own Repairs record via the normal process flow, the same
     * way a real accident report and a repair job order are two separate
     * paper forms. See VehicleAccidentController for who may create/edit/
     * delete which rows (same unit/station visibility scoping used
     * everywhere else in the app).
     */
    public function up(): void
    {
        Schema::create('vehicle_accidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            // Who was behind the wheel — nullable because the assigned driver
            // may not be the one driving (or the vehicle may have no assigned
            // driver at all) at the time of the accident.
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            // Who filed this report — always required, unlike driver_id.
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();

            $table->date('accident_date');
            $table->time('accident_time')->nullable();
            $table->string('location');
            $table->text('description');

            // MINOR / MODERATE / MAJOR — see VehicleAccident::SEVERITIES.
            $table->string('severity');

            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->string('police_report_no')->nullable();

            // A single supporting photo of the damage/scene — kept as one
            // field (not a header+items table) since the task only calls for
            // one photo per report, unlike the multi-line Requisition Slip.
            $table->string('photo_path')->nullable();

            $table->timestamps();

            // Every visibility/scoping query in VehicleAccidentController
            // filters/sorts on these — see scopeToVisibleVehicles()/index().
            $table->index(['vehicle_id', 'accident_date']);
            $table->index('driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_accidents');
    }
};
