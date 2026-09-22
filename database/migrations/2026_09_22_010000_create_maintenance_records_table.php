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
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            $table->enum('maintenance_type', [
                'PMS', 'REPAIR', 'OIL_CHANGE', 'TIRE_CHANGE', 'BATTERY', 'EMERGENCY', 'OTHER',
            ])->default('PMS');

            $table->text('description')->nullable();
            $table->date('service_date');
            $table->unsignedInteger('odometer_km')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('performed_by')->nullable();

            // What this service sets up for next time — this is what the Vehicle's
            // own next_pms_date/odometer_km get synced from (see MaintenanceController).
            $table->date('next_due_date')->nullable();
            $table->unsignedInteger('next_due_odometer_km')->nullable();

            $table->string('attachment_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Supports the record log's default sort, the per-vehicle history lookup,
            // and the due/overdue monitoring queries — all hit these columns directly.
            $table->index(['vehicle_id', 'service_date']);
            $table->index('next_due_date');
            $table->index('maintenance_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
