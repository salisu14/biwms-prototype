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
        Schema::create('item_ledgers', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('item_id')
                ->constrained('item_masters', 'id')
                ->onDelete('restrict');
            $table->foreignId('location_id')
                ->constrained('location_masters', 'id')
                ->onDelete('restrict');
            $table->foreignId('doc_id')
                ->constrained('document_headers', 'id')
                ->onDelete('cascade');
            $table->foreignId('uom_id')
                ->constrained('unit_of_measures', 'id')
                ->onDelete('restrict');
            $table->foreignId('created_by')
                ->constrained('users', 'id')
                ->onDelete('restrict');

            // Transaction data
            $table->enum('entry_type', [
                'RECEIPT',          // Goods received from supplier
                'ISSUE',            // Materials issued to production
                'TRANSFER_IN',      // Transfer into location
                'TRANSFER_OUT',     // Transfer out of location
                'SALE',             // Shipment to customer
                'RETURN',           // Customer return
                'ADJUSTMENT_POS',   // Positive inventory adjustment
                'ADJUSTMENT_NEG',   // Negative inventory adjustment
                'SCRAP',            // Scrap/waste
                'PRODUCTION_OUTPUT' // Finished goods from production
            ]);

            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);

            // Optional: Running balance snapshot for audit
            $table->decimal('balance_after', 18, 4)->nullable();
            $table->decimal('cost_after', 18, 4)->nullable();

            // Lot/serial tracking (for pharmaceutical traceability)
            $table->string('lot_number', 50)->nullable();
            $table->date('expiry_date')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes for performance
            $table->index(['item_id', 'location_id', 'created_at'], 'idx_item_loc_date');
            $table->index(['doc_id', 'entry_type']);
            $table->index(['lot_number', 'item_id']);
            $table->index('created_at');

            // Partitioning support (for high volume)
            // $table->index(['created_at', 'item_id']); // For monthly partitioning
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_ledgers');
    }
};
