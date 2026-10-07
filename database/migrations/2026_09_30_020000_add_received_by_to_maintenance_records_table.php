<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part IV "Certification of Completion" on the Motorpool Service Request
 * Form — "Inspected and Received by" (the driver/representative who
 * certifies the completed service). Date Completed is deliberately not a
 * separate column: service_date already becomes the real completion date
 * the moment a record reaches Completed (see the stage-workflow
 * migration's docblock), so it already serves that role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->string('received_by')->nullable()->after('performed_by');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn('received_by');
        });
    }
};
