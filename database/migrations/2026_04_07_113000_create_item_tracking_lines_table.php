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
        Schema::create('item_tracking_lines', function (Blueprint $table) {
            $table->id();

            // Source document reference
            $table->string('source_type', 50); // sales_order, purchase_order, transfer_order, etc.
            $table->unsignedBigInteger('source_id'); // ID of the source line
            $table->string('source_ref_no', 20)->nullable(); // Document number for reference

            // Item identification
            $table->string('item_no', 20);
            $table->string('variant_code', 20)->nullable();
            $table->string('location_code', 20)->nullable();

            // Tracking information
            $table->string('serial_no', 50)->nullable();
            $table->string('lot_no', 50)->nullable();

            // Dates
            $table->date('expiration_date')->nullable();
            $table->date('warranty_date')->nullable();

            // Quantities (handle both positive and negative)
            $table->decimal('quantity', 18, 4);
            $table->decimal('quantity_base', 18, 4);

            // Buffer fields for calculation
            $table->decimal('quantity_to_handle', 18, 4)->default(0);
            $table->decimal('quantity_to_invoice', 18, 4)->default(0);
            $table->decimal('quantity_handled', 18, 4)->default(0);
            $table->decimal('quantity_invoiced', 18, 4)->default(0);

            // Appl.-to Item Entry (for returns/corrections)
            $table->unsignedBigInteger('appl_to_item_entry')->nullable();

            // Status
            $table->boolean('correction')->default(false);

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['source_type', 'source_id'], 'item_tracking_lines_source');
            $table->index(['item_no', 'lot_no'], 'item_tracking_lines_item_lot');
            $table->index(['item_no', 'serial_no'], 'item_tracking_lines_item_sn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_tracking_lines');
    }
};
