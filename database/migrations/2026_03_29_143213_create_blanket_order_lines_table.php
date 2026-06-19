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
        Schema::create('blanket_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blanket_order_id')->constrained('blanket_orders')->cascadeOnDelete();
            $table->integer('line_number');
            $table->string('type', 20); // ITEM, GL_ACCOUNT, FIXED_ASSET, CHARGE
            $table->string('no', 20)->nullable();
            $table->string('description', 100);
            $table->string('description_2', 50)->nullable();
            $table->string('unit_of_measure', 10)->nullable();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->decimal('quantity_invoiced', 18, 4)->default(0);
            $table->decimal('direct_unit_cost', 18, 4)->default(0);
            $table->decimal('unit_cost_lcy', 18, 4)->default(0);
            $table->decimal('line_amount', 18, 4)->default(0);
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('line_discount_amount', 18, 4)->default(0);
            $table->decimal('inv_discount_amount', 18, 4)->default(0);
            $table->boolean('allow_invoice_disc')->default(true);
            $table->decimal('gross_weight', 18, 4)->nullable();
            $table->decimal('net_weight', 18, 4)->nullable();
            $table->decimal('units_per_parcel', 18, 4)->nullable();
            $table->decimal('unit_volume', 18, 4)->nullable();
            $table->string('appl_to_item_entry', 10)->nullable();
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();
            $table->foreignId('dimension_set_id')->nullable();
            $table->string('item_category_code', 20)->nullable();
            $table->string('product_group_code', 20)->nullable();
            $table->string('location_code', 10)->nullable();
            $table->string('bin_code', 20)->nullable();
            $table->date('expected_receipt_date')->nullable();
            $table->date('planned_receipt_date')->nullable();
            $table->date('requested_receipt_date')->nullable();
            $table->date('promised_receipt_date')->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->integer('purchase_order_line_id')->nullable();
            $table->string('prod_order_no', 20)->nullable();
            $table->string('prod_order_line_no', 10)->nullable();
            $table->string('job_no', 20)->nullable();
            $table->string('job_task_no', 20)->nullable();
            $table->decimal('job_line_amount', 18, 4)->nullable();
            $table->decimal('job_line_amount_lcy', 18, 4)->nullable();
            $table->string('job_currency_code', 10)->nullable();
            $table->decimal('job_currency_factor', 18, 6)->nullable();
            $table->string('whse_posting_group', 10)->nullable();
            $table->string('variant_code', 10)->nullable();
            $table->decimal('qty_per_unit_of_measure', 18, 4)->nullable();
            $table->string('unit_of_measure_code', 10)->nullable();
            $table->decimal('quantity_base', 18, 4)->default(0);
            $table->decimal('outstanding_qty_base', 18, 4)->default(0);
            $table->string('item_charge_base_amount', 18, 4)->nullable();
            $table->boolean('correction')->default(false);
            $table->string('cross_reference_no', 20)->nullable();
            $table->string('cross_reference_type', 10)->nullable();
            $table->string('cross_reference_type_no', 30)->nullable();
            $table->string('transaction_type', 10)->nullable();
            $table->string('transport_method', 10)->nullable();
            $table->string('attached_to_line_no', 10)->nullable();
            $table->string('entry_point', 10)->nullable();
            $table->string('area', 10)->nullable();
            $table->string('transaction_specification', 10)->nullable();
            $table->string('tax_area_code', 20)->nullable();
            $table->boolean('tax_liable')->default(false);
            $table->string('tax_group_code', 10)->nullable();
            $table->decimal('use_tax', 18, 4)->nullable();
            $table->string('vat_bus_posting_group', 10)->nullable();
            $table->string('vat_prod_posting_group', 10)->nullable();
            $table->decimal('vat_base_amount', 18, 4)->default(0);
            $table->decimal('system_created_entry', 18, 4)->nullable();
            $table->decimal('vat_difference', 18, 4)->default(0);
            $table->decimal('inv_disc_amount_to_invoice', 18, 4)->default(0);
            $table->decimal('prepayment_percent', 5, 2)->default(0);
            $table->decimal('prepmt_line_amount', 18, 4)->default(0);
            $table->decimal('prepmt_amt_inv', 18, 4)->default(0);
            $table->decimal('prepmt_amt_incl_vat', 18, 4)->default(0);
            $table->decimal('prepayment_vat_difference', 18, 4)->default(0);
            $table->decimal('prepayment_vat_diff_to_deduct', 18, 4)->default(0);
            $table->decimal('prepayment_vat_diff_deducted', 18, 4)->default(0);
            $table->decimal('qty_to_receive', 18, 4)->default(0);
            $table->decimal('qty_to_invoice', 18, 4)->default(0);
            $table->decimal('qty_to_assign', 18, 4)->default(0);
            $table->decimal('qty_assigned', 18, 4)->default(0);
            $table->string('quantity_received_base', 18, 4)->default(0);
            $table->string('quantity_invoiced_base', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['blanket_order_id', 'line_number']);
            $table->index('blanket_order_id');
            $table->index('type');
            $table->index('no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blanket_order_lines');
    }
};
