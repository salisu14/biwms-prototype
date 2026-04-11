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
        Schema::create('item_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_journal_batch_id')
                ->constrained('item_journal_batches');

            $table->integer('line_number');

            // Entry Type
            $table->enum('entry_type', array_map(fn($case) => $case->value, ItemLedgerEntryType::cases()));

            // Document
            $table->string('document_number', 20);
            $table->date('posting_date');

            // Item
            $table->foreignId('item_id')->constrained('items');
            $table->string('variant_code', 20)->nullable();
            $table->string('description')->nullable();

            // Location & Bin
            $table->foreignId('location_id')->constrained('locations');
            $table->string('bin_code', 20)->nullable();
            $table->foreignId('new_location_id')
                ->nullable()
                ->constrained('locations'); // For transfers
            $table->string('new_bin_code', 20)->nullable();

            // Quantities
            $table->decimal('quantity', 15, 4);
            $table->string('unit_of_measure_code', 20);

            // Costs/Prices
            $table->decimal('unit_amount', 15, 4)->nullable(); // Cost
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('amount', 15, 2)->nullable();

            // Tracking
            $table->string('serial_number', 50)->nullable();
            $table->string('lot_number', 50)->nullable();
            $table->date('expiration_date')->nullable();

            // Posting Groups (for P&L impact)
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups');
            // Product group comes from item

            // Reason
            $table->string('reason_code', 20)->nullable();

            // Posting Status
            $table->boolean('posted')->default(false);
            $table->timestamp('posted_date')->nullable();
            $table->unsignedBigInteger('posted_entry_id')->nullable(); // Links to item ledger

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_journal_lines');
    }
};
