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
        Schema::create('item_tracking_entries', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference to document line
            $table->morphs('trackable'); // trackable_type, trackable_id (SalesShipmentLine, etc.)

            // Item identification
            $table->string('item_no', 20);
            $table->string('variant_code', 20)->nullable();

            // Tracking information (Lot/SN)
            $table->string('serial_no', 50)->nullable();
            $table->string('lot_no', 50)->nullable();

            // Dates
            $table->date('expiration_date')->nullable();
            $table->date('warranty_date')->nullable();

            // Quantities
            $table->decimal('quantity', 18, 4);
            $table->decimal('quantity_base', 18, 4);

            // Entry type (positive/negative)
            $table->enum('entry_type', ['positive', 'negative']);

            // Document reference
            $table->string('document_type', 50); // sales_shipment, purchase_receipt, etc.
            $table->string('document_no', 20);
            $table->integer('document_line_no');

            // Item Ledger Entry reference
            $table->unsignedBigInteger('item_ledg_entry_no')->nullable();

            // Order tracking
            $table->string('order_type', 20)->nullable(); // sales, purchase, transfer
            $table->string('order_no', 20)->nullable();
            $table->integer('order_line_no')->nullable();

            // Correction entry
            $table->boolean('correction')->default(false);

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['item_no', 'lot_no'], 'item_tracking_item_lot');
            $table->index(['item_no', 'serial_no'], 'item_tracking_item_sn');
            $table->index(['trackable_type', 'trackable_id'], 'item_tracking_trackable');
            $table->index(['document_type', 'document_no'], 'item_tracking_document');
            $table->index('item_ledg_entry_no', 'item_tracking_ledg_entry');
        });
        //        Schema::create('item_tracking_entries', function (Blueprint $table) {
        //            $table->id();
        //            $table->morphs('document'); // SalesShipmentLine or SalesOrderLine
        //            $table->string('item_no', 20);
        //            $table->string('variant_code', 10)->nullable();
        //            $table->string('serial_no', 50)->nullable();
        //            $table->string('lot_no', 50)->nullable();
        //            $table->decimal('quantity', 18, 4);
        //            $table->decimal('quantity_base', 18, 4);
        //            $table->date('expiration_date')->nullable();
        //            $table->date('warranty_date')->nullable();
        //            $table->string('entry_type', 20); // Sale, Purchase, Positive Adjmt, etc.
        //            $table->integer('item_ledg_entry_no')->nullable();
        //            $table->boolean('correction')->default(false);
        //            $table->timestamps();
        //
        //            $table->index(['item_no', 'lot_no']);
        //            $table->index(['item_no', 'serial_no']);
        //        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_tracking_entries');
    }
};
