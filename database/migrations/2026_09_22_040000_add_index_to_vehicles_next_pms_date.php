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
        Schema::table('vehicles', function (Blueprint $table) {
            // Both the Vehicle Inventory PMS badge and the new predictive PMS
            // priority ranking (PmsPredictionService::topPriorityVehicles)
            // filter on this column on every request. Same rationale as the
            // indexes already added for users/activity_logs: cheap now,
            // avoids a full table scan once the fleet grows into the
            // thousands.
            $table->index('next_pms_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['next_pms_date']);
        });
    }
};
