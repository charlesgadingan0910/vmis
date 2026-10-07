<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VMIS Additional Updates (PM meeting, Sep 2026) item 1: every vehicle
     * needs to record how it was acquired — Organic (procured by the unit
     * itself), Loaned, or Donated. Defaults every existing row to ORGANIC
     * rather than leaving it null, since every vehicle already on file was
     * acquired one of these ways and a blank/unclassified state isn't a
     * meaningful option here (mirrors how `status` itself has no null state).
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->enum('source', ['ORGANIC', 'LOANED', 'DONATED'])
                ->default('ORGANIC')
                ->after('acquisition_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
