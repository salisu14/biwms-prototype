<?php

use App\Enums\ItemLedgerEntryType;
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
        Schema::create('item_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entry_number')->unique(); // Sequential

            // Source
            $table->enum('entry_type', array_map(fn($case) => $case->value, ItemLedgerEntryType::cases()));

            $table->string('document_type', 30)->nullable();
            $table->string('document_number', 20);
            $table->integer('document_line_number');

            // Item
            $table->foreignId('item_id')->constrained('items');
            $table->string('variant_code', 20)->nullable();

            // Location
            $table->foreignId('location_id')->constrained('locations');
            $table->string('bin_code', 20)->nullable();

            // Quantity
            $table->decimal('quantity', 15, 4);
            $table->decimal('remaining_quantity', 15, 4); // For FIFO/LIFO

            // Tracking
            $table->string('serial_number', 50)->nullable();
            $table->string('lot_number', 50)->nullable();
            $table->date('expiration_date')->nullable();

            // Costs
            $table->decimal('cost_amount_actual', 15, 4)->default(0);
            $table->decimal('cost_amount_expected', 15, 4)->default(0);
            $table->decimal('purchase_amount_actual', 15, 4)->default(0);

            $table->string('source_type')->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();

            // Posting Groups (copied from source)
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups');
            $table->foreignId('general_product_posting_group_id')
                ->constrained('general_product_posting_groups');
            $table->foreignId('inventory_posting_group_id')
                ->constrained('inventory_posting_groups');

            // Dimensions
            $table->json('dimensions')->nullable();

            // Dates
            $table->date('posting_date');
            $table->timestamp('entry_date');

            // Applied Entries (for cost application)
            $table->unsignedBigInteger('applied_entry_id')->nullable();
            $table->boolean('open')->default(true); // For cost application

            $table->timestamps();

            $table->index(['item_id', 'posting_date']);
            $table->index(['location_id', 'posting_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_ledger_entries');
    }
};
