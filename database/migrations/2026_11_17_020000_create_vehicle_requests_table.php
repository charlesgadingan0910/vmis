<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-02 — vehicle requests and dispatch. An APPROVED request is a booking
 * that blocks the same vehicle (and driver) for overlapping dates/times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('purpose', 255);
            $table->string('origin', 150)->nullable();
            $table->string('destination', 150);
            $table->string('passengers', 255)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_role', 40);
            $table->string('status', 15)->default('pending');   // pending | approved | rejected | cancelled
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status', 'start_at', 'end_at']);
            $table->index(['driver_id', 'status', 'start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_requests');
    }
};
