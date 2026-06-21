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
        Schema::create('value_entries', function (Blueprint $table) {
            $table->id();

            // Entry identification
            $table->unsignedBigInteger('entry_no')->unique();
            $table->unsignedBigInteger('item_ledger_entry_no')->nullable();
            $table->unsignedBigInteger('item_ledger_entry_type'); // Enum: PURCHASE, SALE, POSITIVE_ADJUSTMENT, NEGATIVE_ADJUSTMENT, TRANSFER, CONSUMPTION, OUTPUT, CAPACITY, ASSEMBLY, OVERHEAD

            // Source document
            $table->string('source_type', 50)->nullable(); // PRODUCTION_ORDER, PURCHASE_ORDER, SALES_ORDER, TRANSFER_ORDER, JOURNAL
            $table->string('source_no', 50)->nullable();
            $table->unsignedInteger('source_line_no')->nullable();
            $table->string('source_batch_name', 50)->nullable();

            // Item reference
            $table->string('item_no', 50);
            $table->string('variant_code', 50)->nullable();
            $table->string('location_code', 50);
            $table->string('bin_code', 50)->nullable();

            // Posting details
            $table->date('posting_date');
            $table->date('valuation_date')->nullable();
            $table->string('document_type', 50)->nullable();
            $table->string('document_no', 50)->nullable();
            $table->unsignedInteger('document_line_no')->nullable();
            $table->string('description', 255)->nullable();

            // Quantity and costing
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('invoiced_quantity', 18, 4)->default(0);
            $table->string('costing_method', 50)->nullable(); // FIFO, LIFO, AVERAGE, STANDARD, SPECIFIC

            // Cost amounts
            $table->decimal('cost_amount_actual', 18, 4)->default(0);
            $table->decimal('cost_amount_actual_acy', 18, 4)->default(0); // Additional currency
            $table->decimal('cost_amount_expected', 18, 4)->default(0); // For expected cost posting
            $table->decimal('cost_amount_expected_acy', 18, 4)->default(0);

            // Cost components (detailed breakdown)
            $table->decimal('direct_cost_amount', 18, 4)->default(0);
            $table->decimal('indirect_cost_amount', 18, 4)->default(0);
            $table->decimal('overhead_amount', 18, 4)->default(0);
            $table->decimal('variance_amount', 18, 4)->default(0);
            $table->decimal('purchase_variance_amount', 18, 4)->default(0);
            $table->decimal('material_variance_amount', 18, 4)->default(0);
            $table->decimal('capacity_variance_amount', 18, 4)->default(0);
            $table->decimal('capacity_overhead_variance_amount', 18, 4)->default(0);
            $table->decimal('manufacturing_overhead_variance_amount', 18, 4)->default(0);

            // Unit costs
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('unit_cost_acy', 18, 4)->default(0);
            $table->decimal('single_level_material_cost', 18, 4)->default(0);
            $table->decimal('single_level_capacity_cost', 18, 4)->default(0);
            $table->decimal('single_level_subcontracted_cost', 18, 4)->default(0);
            $table->decimal('single_level_overhead_cost', 18, 4)->default(0);
            $table->decimal('single_level_mfg_ovhd_cost', 18, 4)->default(0);
            $table->decimal('rollover_amount', 18, 4)->default(0); // For cost rollover adjustments

            // Capacity specific (for labor/machine entries)
            $table->string('capacity_type', 50)->nullable(); // WORK_CENTER, MACHINE_CENTER
            $table->string('capacity_no', 50)->nullable();
            $table->unsignedInteger('routing_no')->nullable();
            $table->unsignedInteger('routing_reference_no')->nullable();
            $table->string('operation_no', 50)->nullable();
            $table->decimal('work_center_purch_capacity', 18, 4)->default(0);
            $table->decimal('work_center_purch_oh_capacity', 18, 4)->default(0);
            $table->decimal('work_center_purch_direct_cost', 18, 4)->default(0);
            $table->decimal('work_center_purch_ovhd_cost', 18, 4)->default(0);

            // Production order specific
            $table->string('production_order_no', 50)->nullable();
            $table->string('production_order_line_no', 50)->nullable();
            $table->string('production_order_component_line_no', 50)->nullable();
            $table->string('prod_order_line_item_no', 50)->nullable();

            // Purchase/sales specific
            $table->string('purchase_order_no', 50)->nullable();
            $table->string('purchase_order_line_no', 50)->nullable();
            $table->string('sales_order_no', 50)->nullable();
            $table->string('sales_order_line_no', 50)->nullable();
            $table->string('vendor_no', 50)->nullable();
            $table->string('customer_no', 50)->nullable();

            // Item tracking
            $table->string('serial_no', 50)->nullable();
            $table->string('lot_no', 50)->nullable();
            $table->date('expiration_date')->nullable();

            // G/L posting status
            $table->boolean('gl_posted')->default(false);
            $table->date('gl_posting_date')->nullable();
            $table->unsignedBigInteger('gl_entry_no')->nullable();
            $table->string('gl_account_no', 50)->nullable();
            $table->string('balancing_account_no', 50)->nullable();

            // Cost adjustment status
            $table->boolean('cost_adjusted')->default(false);
            $table->date('cost_adjustment_date')->nullable();
            $table->unsignedBigInteger('cost_adjustment_entry_no')->nullable();
            $table->boolean('cost_is_adjusted')->default(false);
            $table->boolean('cost_is_changed_by_user')->default(false);

            // Dimensions for analysis
            $table->string('global_dimension_1_code', 50)->nullable();
            $table->string('global_dimension_2_code', 50)->nullable();
            $table->json('shortcut_dimension_codes')->nullable(); // JSON for dimensions 3-8
            $table->json('dimension_set_id')->nullable();

            // User and system
            $table->string('user_id', 50)->nullable();
            $table->string('source_code', 50)->nullable();
            $table->string('reason_code', 50)->nullable();
            $table->boolean('completely_invoiced')->default(false);
            $table->boolean('last_invoice')->default(false);
            $table->boolean('expected_cost')->default(false);
            $table->boolean('partial_posted')->default(false);
            $table->string('entry_type', 50)->nullable(); // DIRECT_COST, REVALUATION, ROUNDING

            // Adjustment details
            $table->unsignedBigInteger('adjustment_entry_no')->nullable();
            $table->unsignedBigInteger('original_entry_no')->nullable();
            $table->string('original_document_no', 50)->nullable();
            $table->date('original_posting_date')->nullable();

            // Job/project costing (if applicable)
            $table->string('job_no', 50)->nullable();
            $table->string('job_task_no', 50)->nullable();
            $table->string('job_line_type', 50)->nullable();

            // Additional fields for WMS integration
            $table->string('warehouse_activity_no', 50)->nullable();
            $table->unsignedInteger('warehouse_line_no')->nullable();
            $table->string('registering_no', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('item_no');
            $table->index('posting_date');
            $table->index('item_ledger_entry_no');
            $table->index('source_no');
            $table->index('source_type');
            $table->index('production_order_no');
            $table->index('gl_posted');
            $table->index('cost_adjusted');
            $table->index(['item_no', 'posting_date']);
            $table->index(['source_type', 'source_no']);
            $table->index(['item_ledger_entry_type', 'posting_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('value_entries');
    }
};
