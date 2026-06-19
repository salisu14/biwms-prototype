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
        Schema::create('warehouse_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->date('expiration_date')->nullable();
            $table->enum('entry_type', ['positive', 'negative', 'transfer']);
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_base', 15, 4);
            $table->string('unit_of_measure_code', 20);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('total_cost', 15, 4)->nullable();

            // Document references
            $table->string('document_type', 50)->nullable(); // production_order, transfer_order, sales_order, etc.
            $table->string('document_no', 50)->nullable();
            $table->integer('document_line_no')->nullable();

            // Source references
            $table->foreignId('warehouse_activity_line_id')->nullable()->constrained('warehouse_activity_lines')->nullOnDelete();
            $table->foreignId('item_ledger_entry_id')->nullable()->constrained('item_ledger_entries')->nullOnDelete();

            $table->timestamp('entry_timestamp');
            $table->foreignId('created_by')->constrained('users');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['item_id', 'location_id', 'bin_id', 'lot_no', 'serial_no'], 'idx_inventory_flow');
            $table->index(['entry_timestamp']);
            $table->index(['document_type', 'document_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_entries');
    }
};
