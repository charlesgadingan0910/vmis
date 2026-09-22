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
        Schema::table('users', function (Blueprint $table) {
            // "Users table should also reflect who created the account for
            // transparency" — nullOnDelete so removing the creator's own
            // account later never blocks deleting that row.
            $table->foreignId('created_by')->nullable()->after('is_password_changed')
                ->constrained('users')->nullOnDelete();

            // The System Users page filters/scopes on all four of these
            // columns on every request. With no index, each of those does a
            // full table scan — fine at dozens of rows, noticeably slower at
            // thousands. Plain single-column indexes are enough since they're
            // always used as simple equality/whereIn filters, never ranges.
            $table->index('account_type');
            $table->index('unit_id');
            $table->index('station_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
            $table->dropIndex(['account_type']);
            $table->dropIndex(['unit_id']);
            $table->dropIndex(['station_id']);
            $table->dropIndex(['is_active']);
        });
    }
};
