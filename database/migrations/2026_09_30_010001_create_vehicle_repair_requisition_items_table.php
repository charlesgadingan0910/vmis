<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per requested part/material line (the paper form's QTY /
 * PARTICULARS / PRICE table) — see App\Models\VehicleRepairRequisition::
 * totalPrice(), which sums this table's price column the same way the
 * paper form's own TOTAL row does. price is the line's own amount as typed
 * on the form (not qty × a separate unit price — the paper form has no
 * unit-price column, just one PRICE entry per line).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_repair_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_repair_requisition_id');
            $table->unsignedInteger('qty')->nullable();
            $table->string('particulars');
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();

            // Named explicitly: the auto-generated name for this column/table
            // pair exceeds MySQL's 64-character identifier limit.
            $table->foreign('vehicle_repair_requisition_id', 'vrr_items_requisition_fk')
                ->references('id')->on('vehicle_repair_requisitions')
                ->cascadeOnDelete();

            $table->index('vehicle_repair_requisition_id', 'vrr_items_requisition_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_repair_requisition_items');
    }
};
