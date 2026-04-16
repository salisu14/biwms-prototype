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
        Schema::create('purchase_invoice_lines', function (Blueprint $table) {
            $table->id();

            // Parent Invoice
            $table->foreignId('purchase_invoice_id')
                ->constrained('purchase_invoices')
                ->onDelete('cascade');

            // Source Reference
            $table->unsignedBigInteger('po_line_id')->nullable(); // PurchaseOrderLine ID
            $table->integer('po_line_number')->nullable(); // Snapshot of line number

            // Item Information (snapshot at posting)
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->string('item_code', 20);
            $table->string('item_description', 100);
            $table->string('variant_code', 20)->nullable();

            // Posting Groups (copied from item at posting)
            $table->foreignId('general_product_posting_group_id')
                ->nullable()
                ->constrained('general_product_posting_groups');
            $table->foreignId('inventory_posting_group_id')
                ->nullable()
                ->constrained('inventory_posting_groups');

            // G/L Account Posted To (snapshot for audit)
            $table->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts');
            $table->string('gl_account_number', 20)->nullable();
            $table->string('gl_account_name', 100)->nullable();

            // Quantity and UOM
            $table->decimal('quantity', 15, 4);
            $table->string('unit_of_measure_code', 20);
            $table->decimal('qty_per_unit_of_measure', 10, 4)->default(1);
            $table->decimal('quantity_base', 15, 4); // Quantity in base UOM

            // Costs
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('unit_cost_lcy', 15, 4); // In local currency

            // Amounts
            $table->decimal('line_total', 15, 4); // quantity * unit_cost
            $table->decimal('line_discount_amount', 15, 4)->default(0);
            $table->decimal('line_discount_percent', 5, 2)->default(0);

            // VAT
            $table->string('vat_code', 20)->nullable();
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->decimal('vat_amount', 15, 4)->default(0);
            $table->decimal('vat_amount_lcy', 15, 4)->default(0);

            // Total
            $table->decimal('amount_including_vat', 15, 4);
            $table->decimal('amount_including_vat_lcy', 15, 4);

            // Item Tracking (for inventory items)
            $table->string('lot_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->date('expiration_date')->nullable();

            // Job/Project Costing (if applicable)
            $table->string('job_number', 20)->nullable();
            $table->string('job_task_number', 20)->nullable();

            // Dimensions
            $table->json('dimensions')->nullable();

            // Related Entries (for drill-down)
            $table->unsignedBigInteger('item_ledger_entry_id')->nullable();
            $table->unsignedBigInteger('gl_entry_id')->nullable();

            $table->integer('line_number'); // Line sequence

            $table->date('posting_date');

            $table->timestamps();

            // Indexes
            $table->index(['purchase_invoice_id', 'line_number']);
            $table->index(['item_id', 'posting_date']);
            $table->index(['gl_account_id', 'posting_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_lines');
    }
};
