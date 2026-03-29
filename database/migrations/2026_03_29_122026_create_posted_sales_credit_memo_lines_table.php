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
        Schema::create('posted_sales_credit_memo_lines', function (Blueprint $table) {
            $table->id();

            // Parent Credit Memo
            $table->foreignId('posted_sales_credit_memo_id')
                ->constrained('posted_sales_credit_memos')
                ->onDelete('cascade');

            // Source References
            $table->unsignedBigInteger('corrected_invoice_line_id')->nullable();
            $table->unsignedBigInteger('so_line_id')->nullable();
            $table->integer('so_line_number')->nullable();

            // Item Information (snapshot)
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->string('item_code', 20)->nullable();
            $table->string('item_description', 100);
            $table->string('variant_code', 20)->nullable();

            // Posting Groups (snapshot)
            $table->foreignId('general_product_posting_group_id')
                ->nullable()
                ->constrained('general_product_posting_groups');
            $table->foreignId('inventory_posting_group_id')
                ->nullable()
                ->constrained('inventory_posting_groups');

            // G/L Accounts Posted To (snapshot - reversed from invoice)
            $table->foreignId('sales_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('cogs_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('inventory_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('returns_account_id')->nullable()->constrained('chart_of_accounts'); // For non-inventory returns

            // Quantity (negative for returns)
            $table->decimal('quantity', 15, 4); // Negative for returns
            $table->string('unit_of_measure_code', 20);
            $table->decimal('qty_per_unit_of_measure', 10, 4)->default(1);
            $table->decimal('quantity_base', 15, 4); // In base UOM (negative)

            // Pricing (from original invoice)
            $table->decimal('unit_price', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable(); // Cost at time of original sale
            $table->decimal('unit_cost_lcy', 15, 4)->nullable();
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('line_discount_amount', 15, 4)->default(0);

            // Amounts (negative)
            $table->decimal('line_total', 15, 4); // quantity * unit_price (negative)
            $table->decimal('line_amount', 15, 4); // After discount (negative)

            // VAT (negative)
            $table->string('vat_code', 20)->nullable();
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->decimal('vat_amount', 15, 4)->default(0); // Negative
            $table->decimal('amount_including_vat', 15, 4)->default(0); // Negative

            // COGS Reversal (for inventory returns)
            $table->decimal('cost_amount_reversed', 15, 4)->default(0); // Positive (reversing COGS)
            $table->decimal('inventory_amount_reversed', 15, 4)->default(0); // Positive (putting back to inventory)

            // Return Details
            $table->enum('return_type', [
                'FULL',             // Complete return
                'PARTIAL',          // Partial quantity return
                'DAMAGED',          // Damaged goods
                'DEFECTIVE',        // Defective product
                'WRONG_ITEM',       // Shipped wrong item
            ])->default('FULL');

            // Item Tracking (for inventory put-back)
            $table->string('lot_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->date('expiration_date')->nullable();

            $table->date('posting_date'); // When posted to G/L

            // Warehouse Receipt (physical return)
            $table->unsignedBigInteger('warehouse_receipt_id')->nullable();
            $table->string('return_bin_code', 20)->nullable();

            // Related Entries
            $table->unsignedBigInteger('item_ledger_entry_id')->nullable(); // Positive entry putting inventory back
            $table->unsignedBigInteger('gl_entry_id')->nullable();

            // Dimensions
            $table->json('dimensions')->nullable();

            $table->integer('line_number');

            $table->timestamps();

            // Indexes
            $table->index(['posted_sales_credit_memo_id', 'line_number']);
            $table->index(['item_id', 'posting_date']);
            $table->index(['corrected_invoice_line_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posted_sales_credit_memo_lines');
    }
};
