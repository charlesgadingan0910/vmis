<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VMIS Additional Updates item 6: driver registration should capture the
     * DL (Driver's License) Codes — the vehicle-class authorizations printed
     * on the back of a Philippine license (A, A1, B, B1, B2, C, D, BE, CE) —
     * which is a different classification from the existing `license_type`
     * field (Professional/Non-Professional).
     *
     * Alongside that explicit ask, this also adds the other license-back
     * details that are operationally useful for fleet/driver records: the
     * numbered driving-condition restrictions (1-5), the card's serial
     * number, and an emergency contact. Organ Donation status is
     * deliberately left out — it's sensitive personal medical information
     * with no bearing on fleet operations.
     */
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->json('dl_codes')->nullable()->after('license_type');
            $table->json('restriction_codes')->nullable()->after('dl_codes');
            $table->string('license_serial_number')->nullable()->after('restriction_codes');
            $table->string('emergency_contact_name')->nullable()->after('contact_number');
            $table->string('emergency_contact_address')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_number')->nullable()->after('emergency_contact_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'dl_codes',
                'restriction_codes',
                'license_serial_number',
                'emergency_contact_name',
                'emergency_contact_address',
                'emergency_contact_number',
            ]);
        });
    }
};
