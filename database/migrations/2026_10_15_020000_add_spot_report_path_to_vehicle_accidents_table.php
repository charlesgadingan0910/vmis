<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Spot Report" — a separate uploaded document from the accident's own
 * photo (photo_path), for the formal incident report file. Same shape
 * (nullable path string on the public disk) and same conventions as
 * photo_path, see VehicleAccidentController::validationRules()/store().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_accidents', function (Blueprint $table) {
            $table->string('spot_report_path')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_accidents', function (Blueprint $table) {
            $table->dropColumn('spot_report_path');
        });
    }
};
