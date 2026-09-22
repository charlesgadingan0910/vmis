<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds "DRIVER" as a selectable account type. Placed after every other
     * level (max existing level + 1) rather than a hardcoded number, since
     * this table has no seeder anywhere in this codebase — its existing rows
     * were set up outside of Laravel migrations — so the real current levels
     * can't be assumed from here.
     */
    public function up(): void
    {
        if (DB::table('account_types')->where('type', 'DRIVER')->exists()) {
            return;
        }

        $nextLevel = (int) (DB::table('account_types')->max('level') ?? 0) + 1;

        DB::table('account_types')->insert([
            'level' => $nextLevel,
            'type' => 'DRIVER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('account_types')->where('type', 'DRIVER')->delete();
    }
};
