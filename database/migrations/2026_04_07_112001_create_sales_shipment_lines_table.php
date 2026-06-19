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
        Schema::create('sales_shipment_lines', function (Blueprint $table) {
            $table->id();

            // Document Reference (Composite key pattern like BC)
            $table->foreignId('sales_shipment_header_id')->constrained('sales_shipment_headers');
            $table->string('document_no', 20);
            $table->integer('line_no'); // BC: "Line No." - 10000, 20000, etc.

            // Line Type & Item Reference
            $table->string('type', 20); // Item, Resource, G/L Account, Fixed Asset, Charge
            $table->string('no', 20)->nullable(); // Item No / Account No
            $table->string('variant_code', 10)->nullable();
            $table->string('description', 100);
            $table->string('description_2', 50)->nullable();

            // Location & Bin (Warehouse)
            $table->string('location_code', 10)->nullable();
            $table->string('bin_code', 20)->nullable();
            $table->string('posting_group', 20)->nullable();

            // Quantities (BC tracking)
            $table->decimal('quantity', 18, 4); // Total quantity shipped
            $table->decimal('quantity_base', 18, 4); // Base UOM quantity
            $table->decimal('qty_shipped_not_invoiced', 18, 4)->default(0);
            $table->decimal('quantity_invoiced', 18, 4)->default(0);
            $table->decimal('qty_invoiced_base', 18, 4)->default(0);

            // Unit of Measure
            $table->string('unit_of_measure', 50);
            $table->string('unit_of_measure_code', 10);
            $table->decimal('qty_per_unit_of_measure', 18, 4)->default(1);

            // Pricing (Historical snapshot at shipment time)
            $table->decimal('unit_price', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('unit_cost_lcy', 18, 4)->default(0); // Local currency
            $table->decimal('line_discount_pct', 5, 2)->default(0);
            $table->decimal('line_discount_amount', 18, 4)->default(0);
            $table->decimal('line_amount', 18, 4); // Net amount
            $table->decimal('amount', 18, 4);
            $table->decimal('amount_including_vat', 18, 4);
            $table->decimal('vat_base_amount', 18, 4)->default(0);
            $table->decimal('vat_pct', 5, 2)->default(0);
            $table->boolean('allow_invoice_disc')->default(true);
            $table->boolean('allow_line_disc')->default(true);

            // Order Tracking (BC: "Order No.", "Order Line No.")
            $table->string('order_no', 20)->nullable();
            $table->integer('order_line_no')->nullable();

            // Drop Shipment / Special Order
            $table->boolean('drop_shipment')->default(false);
            $table->string('purchase_order_no', 20)->nullable();
            $table->integer('purch_order_line_no')->nullable();
            $table->boolean('special_order')->default(false);
            $table->string('special_order_purchase_no', 20)->nullable();
            $table->integer('special_order_purch_line_no')->nullable();

            // Blanket Order Reference
            $table->string('blanket_order_no', 20)->nullable();
            $table->integer('blanket_order_line_no')->nullable();

            // Dimensions
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();
            $table->unsignedBigInteger('dimension_set_id')->nullable();

            // Item Tracking (Lot/SN)
            $table->string('serial_no', 50)->nullable();
            $table->string('lot_no', 50)->nullable();
            $table->date('expiration_date')->nullable();
            $table->integer('appl_to_item_entry')->nullable(); // Item Ledger Entry No
            $table->integer('item_shpt_entry_no')->nullable(); // Posted entry no
            $table->integer('appl_from_item_entry')->nullable();

            // Posting Groups
            $table->string('gen_bus_posting_group', 20)->nullable();
            $table->string('gen_prod_posting_group', 20)->nullable();
            $table->string('vat_prod_posting_group', 20)->nullable();
            $table->string('tax_group_code', 20)->nullable();
            $table->boolean('tax_liable')->default(false);
            $table->string('tax_area_code', 20)->nullable();

            // Item Charges
            $table->decimal('item_charge_base_amount', 18, 4)->nullable();
            $table->boolean('allow_item_charge_assignment')->default(true);
            $table->decimal('qty_to_assign', 18, 4)->default(0);
            $table->decimal('qty_assigned', 18, 4)->default(0);

            // Physical Attributes
            $table->decimal('gross_weight', 18, 4)->default(0);
            $table->decimal('net_weight', 18, 4)->default(0);
            $table->decimal('units_per_parcel', 18, 4)->default(0);
            $table->decimal('unit_volume', 18, 4)->default(0);

            // Planning & Shipping
            $table->date('shipment_date');
            $table->date('requested_delivery_date')->nullable();
            $table->date('promised_delivery_date')->nullable();
            $table->date('planned_delivery_date')->nullable();
            $table->date('planned_shipment_date')->nullable();
            $table->integer('shipping_time')->nullable();
            $table->integer('outbound_whse_handling_time')->nullable();

            // Job/Project (BC Integration)
            $table->string('job_no', 20)->nullable();
            $table->string('job_task_no', 20)->nullable();
            $table->integer('job_contract_entry_no')->nullable();

            // Fixed Asset Fields
            $table->date('fa_posting_date')->nullable();
            $table->string('depreciation_book_code', 10)->nullable();
            $table->boolean('depr_until_fa_posting_date')->default(false);
            $table->string('duplicate_in_depreciation_book', 10)->nullable();
            $table->boolean('use_duplication_list')->default(false);

            // Item Reference (Cross References)
            $table->string('item_reference_no', 50)->nullable();
            $table->string('item_reference_type', 30)->nullable();
            $table->string('item_reference_type_no', 30)->nullable();
            $table->string('item_reference_unit_of_measure', 10)->nullable();
            $table->string('ic_item_reference_no', 50)->nullable(); // Intercompany

            // Intercompany
            $table->string('ic_partner_ref_type', 30)->nullable();
            $table->string('ic_partner_reference', 20)->nullable();

            // Return & Correction
            $table->boolean('correction')->default(false);
            $table->string('return_reason_code', 10)->nullable();

            // Line Structure
            $table->integer('attached_to_line_no')->nullable(); // Comment/Description lines attached to
            $table->string('customer_price_group', 10)->nullable();
            $table->string('customer_disc_group', 20)->nullable();
            $table->string('work_type_code', 10)->nullable();

            // System Fields
            $table->date('posting_date');
            $table->string('currency_code', 10)->nullable();
            $table->string('responsibility_center', 10)->nullable();
            $table->string('item_category_code', 20)->nullable();
            $table->boolean('nonstock')->default(false);
            $table->string('purchasing_code', 10)->nullable();

            // Cross References
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines');

            $table->timestamps();

            // Composite unique key (Document No + Line No)
            $table->unique(['document_no', 'line_no']);

            // Performance indexes
            $table->index('no');
            $table->index('order_no');
            $table->index(['sales_shipment_header_id', 'line_no']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_shipment_lines');
    }
};
