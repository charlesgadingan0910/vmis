<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a DRIVER-type login account to its Driver profile record (license
     * number, contact info, etc.) — previously there was no connection at all
     * between the `users` (login accounts) and `drivers` (profile records)
     * tables. Nullable because every non-DRIVER account leaves this blank,
     * and unique because one Driver profile can only ever back one login
     * account (MySQL allows any number of NULLs alongside a unique index, so
     * this doesn't collide with every other account type sharing NULL here).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('station_id')->constrained('drivers')->nullOnDelete();
            $table->unique('driver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['driver_id']);
            $table->dropConstrainedForeignId('driver_id');
        });
    }
};
