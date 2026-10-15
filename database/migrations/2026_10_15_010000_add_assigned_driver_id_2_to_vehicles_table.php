<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A vehicle can now have up to TWO assigned drivers at once — see
 * VehicleController's Register/Edit modal. assigned_driver_id (slot 1)
 * keeps its existing meaning; this adds a second, independent slot
 * with the same nullable/nullOnDelete shape so dropping a driver record
 * never leaves a vehicle pointing at a deleted driver in either slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('assigned_driver_id_2')
                ->nullable()
                ->after('assigned_driver_id')
                ->constrained('drivers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['assigned_driver_id_2']);
            $table->dropColumn('assigned_driver_id_2');
        });
    }
};
