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
        Schema::create('warehouse_setup', function (Blueprint $table) {
            $table->id();
            // General
            $table->boolean('location_mandatory')->default(false);
            $table->boolean('bin_mandatory')->default(false);

            // Warehouse Documents Required
            $table->boolean('require_pick')->default(false);
            $table->boolean('require_putaway')->default(false);
            $table->boolean('require_receive')->default(false);
            $table->boolean('require_shipment')->default(false);

            // Advanced WMS
            $table->boolean('directed_putaway_and_pick')->default(false);
            $table->string('warehouse_receipt_nos')->nullable();
            $table->string('warehouse_shipment_nos')->nullable();
            $table->string('internal_putaway_nos')->nullable();
            $table->string('internal_pick_nos')->nullable();

            // Bin Policies
            $table->enum('bin_capacity_policy', ['Never Check', 'Check', 'Prohibit'])->default('Never Check');
            $table->boolean('allow_breakbulk')->default(false);
            $table->string('putaway_template_nos')->nullable();
            $table->boolean('pick_according_to_fefo')->default(false);
            $table->enum('default_bin_selection', ['Fixed Bin', 'Last Bin Used', 'WMS Default'])->default('Fixed Bin');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_setups');
    }
};
