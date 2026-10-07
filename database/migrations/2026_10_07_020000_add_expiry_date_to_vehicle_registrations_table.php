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
            // When this registration bundle (OR/CR/Insurance) stops being valid —
            // what "due for registration" is measured against on Vehicle Inventory.
            // Nullable: older rows uploaded before this was tracked simply have no
            // expiry on file, so they're silently excluded from due/overdue alerts
            // rather than erroring.
            $table->date('expiry_date')->nullable()->after('insurance_file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_registrations', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
        });
    }
};
