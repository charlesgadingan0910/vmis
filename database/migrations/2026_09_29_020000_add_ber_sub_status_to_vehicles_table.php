<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VMIS Additional Updates item 2: when a vehicle's status is BER, it now
     * carries a sub-status — "For Disposal" or "Disposed" — with a disposal
     * date once it's actually Disposed. Both columns stay null for every
     * non-BER vehicle (enforced in VehicleController, not just left to the
     * form) since neither means anything outside the BER status.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->enum('ber_sub_status', ['FOR_DISPOSAL', 'DISPOSED'])->nullable()->after('status');
            $table->date('disposal_date')->nullable()->after('ber_sub_status');
        });

        // Any vehicle already on file as BER before this update existed gets a
        // starting sub-status of "For Disposal" rather than being left blank —
        // an admin can change it to "Disposed" from the Edit modal if that's
        // actually accurate.
        DB::table('vehicles')->where('status', 'BER')->update(['ber_sub_status' => 'FOR_DISPOSAL']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['ber_sub_status', 'disposal_date']);
        });
    }
};
