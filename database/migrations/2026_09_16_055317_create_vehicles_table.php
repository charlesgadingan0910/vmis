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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number')->unique();
            $table->string('engine_number')->nullable();
            $table->string('chassis_number')->nullable();
            $table->string('make');
            $table->string('model');

            $table->unsignedSmallInteger('year_model')->nullable();
            $table->string('color')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->unsignedInteger('unit_id')->nullable();
            $table->unsignedInteger('station_id')->nullable();
            $table->unsignedInteger('odometer_km')->default(0);
            $table->date('next_pms_date')->nullable();
            $table->enum('status', ['active', 'under_maintenance', 'decommissioned'])->default('active');
            $table->enum('is_active', ['1', '0'])->default('1');
            $table->string('qr_code')->unique()->nullable();

            // Updated relationships
            $table->foreignId('vehicle_type_id')->constrained('vehicle_types')->cascadeOnDelete();
            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
