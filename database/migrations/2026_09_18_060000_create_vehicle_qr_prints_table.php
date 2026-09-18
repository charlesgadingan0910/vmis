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
        Schema::create('vehicle_qr_prints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printed_by')->constrained('users');
            // 'single' = printed from the individual QR view modal, 'bulk' = printed
            // as part of a multi-select batch — kept separate mainly for the audit trail.
            $table->enum('context', ['single', 'bulk'])->default('single');
            $table->timestamp('printed_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_qr_prints');
    }
};
