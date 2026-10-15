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
            // dropConstrainedForeignId() already drops the foreign key
            // constraint AND the column (which takes its unique index with
            // it). A separate dropUnique(['driver_id']) call BEFORE this
            // fails on MySQL/InnoDB with error 1553 ("needed in a foreign
            // key constraint") — the unique index backs the FK, so it can't
            // be dropped out from under a constraint that still points at
            // it. Dropping the FK first (as part of this one call) removes
            // that dependency, so the index/column come off cleanly.
            $table->dropConstrainedForeignId('driver_id');
        });
    }
};
