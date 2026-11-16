<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-01 — changes that a second, higher-level user must approve before they
 * take effect (driver assignment changes and vehicle disposal). The original
 * request is stored as-is in `payload` and replayed when it is approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);                 // e.g. assignment.assign, vehicle.update, vehicle.destroy
            $table->string('category', 20);             // assignment | disposal
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('summary', 255);
            $table->json('payload')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_role', 40);
            $table->string('status', 15)->default('pending');   // pending | approved | rejected | cancelled
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->string('result_message', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index(['vehicle_id', 'category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
