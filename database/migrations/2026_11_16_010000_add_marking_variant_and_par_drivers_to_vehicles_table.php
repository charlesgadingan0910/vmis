<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New vehicle-record fields: whether the vehicle is Marked or Unmarked, its
 * Variant (e.g. "4X2 MT"), and the Primary / Alternate PAR (Property
 * Acknowledgement Receipt) drivers — the people accountable for the vehicle
 * on paper. PAR drivers are separate from the operational "assigned drivers"
 * and are NOT written to driver_assignments. All columns are nullable so
 * existing vehicles stay valid; PAR driver FKs null out if a driver is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('marked_status', 10)->nullable()->after('color');
            $table->string('variant', 50)->nullable()->after('model');
            $table->foreignId('par_driver_id')->nullable()->after('assigned_driver_id_2')
                ->constrained('drivers')->nullOnDelete();
            $table->foreignId('par_driver_id_2')->nullable()->after('par_driver_id')
                ->constrained('drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['par_driver_id']);
            $table->dropForeign(['par_driver_id_2']);
            $table->dropColumn(['marked_status', 'variant', 'par_driver_id', 'par_driver_id_2']);
        });
    }
};
