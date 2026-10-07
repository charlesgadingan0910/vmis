<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicle_registrations', function (Blueprint $table) {
            // Nullable — existing registration rows uploaded before this column
            // existed simply have no insurance on file; the Docs modal shows
            // those as "Not uploaded" rather than breaking. New registrations
            // (initial and annual re-registration) require it going forward,
            // enforced in VehicleController, not here.
            $table->string('insurance_file_path')->nullable()->after('cr_file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_registrations', function (Blueprint $table) {
            $table->dropColumn('insurance_file_path');
        });
    }
};
