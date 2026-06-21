<?php

use App\Enums\ItemLedgerEntryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('item_journal_batches')->cascadeOnDelete();
            $table->integer('line_no')->default(10000);
            $table->date('posting_date');

            // Core Posting Logic
            $table->string('entry_type'); // Enum: positive_adj, negative_adj, purchase, sale, transfer, consumption, output
            $table->string('document_no', 50);
            $table->string('external_document_no', 50)->nullable();

            // Item Information
            $table->foreignId('item_id')->constrained('items');
            $table->string('variant_code', 20)->nullable();
            $table->string('description', 100)->nullable();
            $table->string('unit_of_measure_code', 20);

            // Quantities
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_base', 15, 4); // Calculated in base UOM

            // Warehouse Location (Source/Current)
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->foreignId('bin_id')->nullable()->constrained('bins');

            // Transfer Destination (Only for 'transfer' type)
            $table->foreignId('new_location_id')->nullable()->constrained('locations');
            $table->foreignId('new_zone_id')->nullable()->constrained('zones');
            $table->foreignId('new_bin_id')->nullable()->constrained('bins');

            // Tracking
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('warranty_date')->nullable();

            // Valuation & Financials
            $table->decimal('unit_amount', 15, 4)->nullable(); // Sales/Purchase Price
            $table->decimal('unit_cost', 15, 4)->nullable();   // Inventory Cost
            $table->decimal('amount', 15, 4)->nullable();      // quantity * unit_amount
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->string('currency_code', 10)->nullable();
            $table->decimal('amount_lcy', 15, 4)->nullable();  // Amount in Local Currency

            // Posting Setup
            $table->foreignId('gen_bus_posting_group_id')->nullable()->constrained('general_business_posting_groups');
            $table->foreignId('inventory_posting_group_id')->nullable()->constrained('inventory_posting_groups');

            // Dimensions & Source
            $table->json('dimension_set_entry')->nullable();
            $table->string('source_code', 20)->nullable();
            $table->string('reason_code', 20)->nullable();

            // Status & Ledger Links
            $table->boolean('posted')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('item_ledger_entry_id')->nullable();
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();

            // Indexes
            $table->unique(['batch_id', 'line_no']);
            $table->index(['posting_date', 'item_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_journal_lines');
    }
};
