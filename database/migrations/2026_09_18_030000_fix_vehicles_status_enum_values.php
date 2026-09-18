<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Defensive: normalize any rows that may already exist under the old enum
        // values before changing the column definition (the table is very likely
        // still empty at this point, but this costs nothing either way).
        DB::table('vehicles')->where('status', 'active')->update(['status' => 'SERVICEABLE']);
        DB::table('vehicles')->where('status', 'under_maintenance')->update(['status' => 'UNSERVICEABLE']);
        DB::table('vehicles')->where('status', 'decommissioned')->update(['status' => 'BER']);

        Schema::table('vehicles', function (Blueprint $table) {
            $table->enum('status', ['SERVICEABLE', 'UNSERVICEABLE', 'BER'])
                ->default('SERVICEABLE')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('vehicles')->where('status', 'SERVICEABLE')->update(['status' => 'active']);
        DB::table('vehicles')->where('status', 'UNSERVICEABLE')->update(['status' => 'under_maintenance']);
        DB::table('vehicles')->where('status', 'BER')->update(['status' => 'decommissioned']);

        Schema::table('vehicles', function (Blueprint $table) {
            $table->enum('status', ['active', 'under_maintenance', 'decommissioned'])
                ->default('active')
                ->change();
        });
    }
};
