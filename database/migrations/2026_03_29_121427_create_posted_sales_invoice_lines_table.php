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
        Schema::create('posted_sales_invoice_lines', function (Blueprint $table) {
            $table->id();

            // Parent Invoice
            $table->foreignId('posted_sales_invoice_id')
                ->constrained('posted_sales_invoices')
                ->onDelete('cascade');

            // Source Reference
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

            // G/L Accounts Posted To (snapshot)
            $table->foreignId('sales_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('cogs_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('inventory_account_id')->nullable()->constrained('chart_of_accounts');

            // Quantity
            $table->decimal('quantity', 15, 4);
            $table->string('unit_of_measure_code', 20);
            $table->decimal('qty_per_unit_of_measure', 10, 4)->default(1);
            $table->decimal('quantity_base', 15, 4);

            // Pricing
            $table->decimal('unit_price', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('unit_cost_lcy', 15, 4)->nullable();
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('line_discount_amount', 15, 4)->default(0);

            // Amounts
            $table->decimal('line_total', 15, 4);
            $table->decimal('line_amount', 15, 4);

            // VAT
            $table->string('vat_code', 20)->nullable();
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->decimal('vat_amount', 15, 4)->default(0);
            $table->decimal('amount_including_vat', 15, 4)->default(0);

            // COGS Recognition
            $table->decimal('cost_amount', 15, 4)->default(0); // COGS posted
            $table->decimal('profit_amount', 15, 4)->default(0); // line_amount - cost_amount

            // Item Tracking
            $table->string('lot_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->date('expiration_date')->nullable();

            // Related Entries
            $table->unsignedBigInteger('item_ledger_entry_id')->nullable();
            $table->unsignedBigInteger('shipment_id')->nullable();

            // Dimensions
            $table->json('dimensions')->nullable();

            $table->integer('line_number');

            $table->timestamps();

            // Indexes
            $table->index(['posted_sales_invoice_id', 'line_number']);
            $table->index(['item_id', 'posting_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posted_sales_invoice_lines');
    }
};
