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
        Schema::create('reservation_entries', function (Blueprint $table) {
            $table->id();

            // Entry number (BC: "Entry No.")
            $table->unsignedBigInteger('entry_no')->unique();

            // Item identification
            $table->string('item_no', 20);
            $table->string('variant_code', 20)->nullable();
            $table->string('location_code', 20);

            // Tracking information
            $table->string('serial_no', 50)->nullable();
            $table->string('lot_no', 50)->nullable();

            // Quantities
            $table->decimal('quantity', 18, 4);
            $table->decimal('quantity_base', 18, 4);

            // Reservation status
            $table->enum('reservation_status', ['reservation', 'tracking', 'surplus', 'prospect'])->default('reservation');

            // Source of reservation
            $table->string('source_type', 50); // sales_order, purchase_order, item_journal, etc.
            $table->unsignedBigInteger('source_id');
            $table->integer('source_ref_no')->nullable(); // Line no or batch line no
            $table->string('source_subtype', 20)->nullable(); // e.g., '0' for Order, '1' for Invoice

            // Binding to other entries
            $table->unsignedBigInteger('binding_entry_no')->nullable(); // Links reservation to tracking

            // Expected receipt/ship date
            $table->date('expected_receipt_date')->nullable();
            $table->date('shipment_date')->nullable();

            // Serial/Lot specific fields
            $table->date('expiration_date')->nullable();
            $table->date('warranty_date')->nullable();

            // Qty. to Handle/Invoice (for posting)
            $table->decimal('qty_to_handle', 18, 4)->default(0);
            $table->decimal('qty_to_invoice', 18, 4)->default(0);

            // Correction
            $table->boolean('correction')->default(false);

            // Item Ledger Entry reference (when posted)
            $table->unsignedBigInteger('item_ledg_entry_no')->nullable();

            // Planning fields
            $table->boolean('planning_level')->default(false);
            $table->string('planning_line_no', 20)->nullable();

            $table->timestamp('reservation_date')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['item_no', 'location_code'], 'reserv_entry_item_loc');
            $table->index(['item_no', 'lot_no', 'serial_no'], 'reserv_entry_tracking');
            $table->index(['source_type', 'source_id'], 'reserv_entry_source');
            $table->index('entry_no', 'reserv_entry_no');
            $table->index('binding_entry_no', 'reserv_entry_binding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_entries');
    }
};
