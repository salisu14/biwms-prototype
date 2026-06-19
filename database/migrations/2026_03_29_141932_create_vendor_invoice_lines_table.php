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
        // Vendor Invoice Lines (detail)
        Schema::create('vendor_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->onDelete('cascade');
            $table->integer('line_number');

            // Line type
            $table->string('type')->default('ITEM'); // ITEM, GL_ACCOUNT, FIXED_ASSET, CHARGE

            // Item/Account reference
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets');

            // Description
            $table->text('description');
            $table->string('description_2')->nullable();

            // Quantity and unit
            $table->decimal('quantity', 15, 4)->default(0);
            $table->string('unit_of_measure_code')->nullable();
            $table->decimal('direct_unit_cost', 15, 4)->default(0);

            // Amounts
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('line_discount_amount', 15, 2)->default(0);
            $table->decimal('line_amount', 15, 2)->default(0);

            // Tax
            $table->string('tax_group_code')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);

            // Source document (3-way matching)
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');
            $table->integer('purchase_order_line_no')->nullable();
            $table->foreignId('purchase_receipt_id')->nullable()->constrained('purchase_receipts');
            $table->integer('purchase_receipt_line_no')->nullable();

            // Dimensions
            $table->string('shortcut_dimension_1_code')->nullable();
            $table->string('shortcut_dimension_2_code')->nullable();
            $table->unsignedBigInteger('dimension_set_id')->nullable();

            // CapEx project link
            $table->foreignId('capex_project_id')->nullable()->constrained('capex_projects');
            $table->foreignId('capex_project_line_id')->nullable()->constrained('capex_project_lines');

            // Job/Cost tracking
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders');

            $table->timestamps();

            $table->unique(['vendor_invoice_id', 'line_number']);
            $table->index(['purchase_order_id', 'purchase_order_line_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_invoice_lines');
    }
};
