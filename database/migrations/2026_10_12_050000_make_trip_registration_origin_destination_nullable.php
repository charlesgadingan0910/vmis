<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A driver-declared Emergency Trip (see
     * Api\TripRegistrationController::startEmergency()) creates its own
     * TripRegistration the instant GPS tracking starts — there's no time
     * to ask for an origin/destination first, and the driver's ending
     * form captures those afterwards instead (see trips_screen.dart's
     * _GpsTripEndingForm). Every other registration (admin-created, any
     * trip_type, including an admin-created Emergency one) still always
     * has both set at creation time — this only loosens what the column
     * itself allows, not the admin Register Trip form's own `required`
     * validation.
     */
    public function up(): void
    {
        Schema::table('trip_registrations', function (Blueprint $table) {
            $table->string('origin', 150)->nullable()->change();
            $table->string('destination', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trip_registrations', function (Blueprint $table) {
            $table->string('origin', 150)->nullable(false)->change();
            $table->string('destination', 150)->nullable(false)->change();
        });
    }
};
