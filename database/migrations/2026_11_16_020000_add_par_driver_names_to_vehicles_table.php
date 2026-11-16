<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A PAR (Property Acknowledgement Receipt) holder can be recorded by typed name
 * before they exist as a driver record. The name is kept here until an admin
 * links it to a real driver from Driver Management (par_driver_id / _2 are then
 * set and the typed name is cleared).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('par_driver_name', 100)->nullable()->after('par_driver_id_2');
            $table->string('par_driver_name_2', 100)->nullable()->after('par_driver_name');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['par_driver_name', 'par_driver_name_2']);
        });
    }
};
