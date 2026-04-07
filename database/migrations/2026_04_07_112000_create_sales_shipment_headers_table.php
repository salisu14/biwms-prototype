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
        Schema::create('sales_shipment_headers', function (Blueprint $table) {
            // Primary Document Fields (BC Table 110)
            $table->id();
            $table->string('document_no', 20)->unique(); // BC: "No."
            $table->foreignId('sales_order_id')->constrained('sales_orders')->nullable(); // BC: "Order No."
            $table->string('order_no', 20)->nullable(); // Original order number reference

            // Customer Information (Sell-to)
            $table->string('sell_to_customer_no', 20); // BC: "Sell-to Customer No."
            $table->string('sell_to_customer_name', 100);
            $table->string('sell_to_customer_name_2', 50)->nullable();
            $table->string('sell_to_address', 100)->nullable();
            $table->string('sell_to_address_2', 50)->nullable();
            $table->string('sell_to_city', 30)->nullable();
            $table->string('sell_to_post_code', 20)->nullable();
            $table->string('sell_to_county', 30)->nullable();
            $table->string('sell_to_country_region_code', 10)->nullable();
            $table->string('sell_to_contact', 100)->nullable();
            $table->string('sell_to_contact_no', 20)->nullable();
            $table->string('sell_to_phone_no', 30)->nullable();
            $table->string('sell_to_email', 80)->nullable();

            // Bill-to Information
            $table->string('bill_to_customer_no', 20);
            $table->string('bill_to_name', 100);
            $table->string('bill_to_name_2', 50)->nullable();
            $table->string('bill_to_address', 100)->nullable();
            $table->string('bill_to_address_2', 50)->nullable();
            $table->string('bill_to_city', 30)->nullable();
            $table->string('bill_to_post_code', 20)->nullable();
            $table->string('bill_to_county', 30)->nullable();
            $table->string('bill_to_country_region_code', 10)->nullable();
            $table->string('bill_to_contact', 100)->nullable();
            $table->string('bill_to_contact_no', 20)->nullable();

            // Ship-to Information (Alternative Address)
            $table->string('ship_to_code', 10)->nullable(); // BC: "Ship-to Code"
            $table->string('ship_to_name', 100)->nullable();
            $table->string('ship_to_name_2', 50)->nullable();
            $table->string('ship_to_address', 100)->nullable();
            $table->string('ship_to_address_2', 50)->nullable();
            $table->string('ship_to_city', 30)->nullable();
            $table->string('ship_to_post_code', 20)->nullable();
            $table->string('ship_to_county', 30)->nullable();
            $table->string('ship_to_country_region_code', 10)->nullable();
            $table->string('ship_to_contact', 100)->nullable();
            $table->string('ship_to_phone_no', 30)->nullable();

            // Dates
            $table->date('order_date'); // BC: "Order Date"
            $table->date('posting_date'); // BC: "Posting Date" (actual shipment date)
            $table->date('shipment_date'); // BC: "Shipment Date" (planned)
            $table->date('document_date');
            $table->date('due_date')->nullable();
            $table->date('payment_discount_date')->nullable();
            $table->date('requested_delivery_date')->nullable();
            $table->date('promised_delivery_date')->nullable();

            // Shipping Details
            $table->string('shipment_method_code', 10)->nullable(); // BC: "Shipment Method Code"
            $table->string('shipping_agent_code', 10)->nullable(); // BC: "Shipping Agent Code"
            $table->string('shipping_agent_service_code', 10)->nullable();
            $table->string('package_tracking_no', 50)->nullable();
            $table->string('transport_method', 10)->nullable();
            $table->string('exit_point', 10)->nullable();
            $table->string('area', 10)->nullable();

            // Financial Setup
            $table->string('currency_code', 10)->nullable();
            $table->decimal('currency_factor', 18, 6)->default(1);
            $table->string('customer_posting_group', 20);
            $table->string('gen_bus_posting_group', 20);
            $table->string('vat_bus_posting_group', 20);
            $table->string('tax_area_code', 20)->nullable();
            $table->boolean('tax_liable')->default(false);
            $table->string('tax_group_code', 20)->nullable();
            $table->decimal('vat_base_discount_pct', 5, 2)->default(0);
            $table->string('invoice_disc_code', 20)->nullable();
            $table->string('customer_disc_group', 20)->nullable();
            $table->string('customer_price_group', 10)->nullable();
            $table->boolean('prices_including_vat')->default(false);
            $table->boolean('allow_line_disc')->default(true);

            // Payment Terms
            $table->string('payment_terms_code', 10)->nullable();
            $table->string('payment_method_code', 10)->nullable();
            $table->decimal('payment_discount_pct', 5, 2)->default(0);

            // Location & Warehouse
            $table->string('location_code', 10)->nullable();
            $table->string('responsibility_center', 10)->nullable();
            $table->integer('outbound_whse_handling_time')->nullable(); // Days
            $table->integer('shipping_time')->nullable(); // Days

            // Dimensions (BC Dimension Set)
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();
            $table->unsignedBigInteger('dimension_set_id')->nullable();
            $table->foreign('dimension_set_id')->references('id')->on('dimension_sets');

            // Salesperson & Campaign
            $table->string('salesperson_code', 20)->nullable();
            $table->string('campaign_no', 20)->nullable();
            $table->string('opportunity_no', 20)->nullable();

            // Document Tracking
            $table->string('your_reference', 35)->nullable();
            $table->string('external_document_no', 35)->nullable();
            $table->string('quote_no', 20)->nullable();
            $table->string('blanket_order_no', 20)->nullable();

            // Posting & Status
            $table->boolean('correction')->default(false); // BC: "Correction"
            $table->string('source_code', 10)->nullable();
            $table->string('reason_code', 10)->nullable();
            $table->string('user_id', 50)->nullable();
            $table->boolean('comment')->default(false);
            $table->integer('no_printed')->default(0);
            $table->string('on_hold', 3)->nullable();

            // Prepayment
            $table->decimal('prepayment_pct', 5, 2)->default(0);

            // Integration Fields
            $table->uuid('customer_id')->nullable(); // BC: "Customer Id"
            $table->uuid('bill_to_customer_id')->nullable();
            $table->string('document_id', 50)->nullable(); // External GUID

            // Ledger Entry References
            $table->string('applies_to_doc_type', 50)->nullable();
            $table->string('applies_to_doc_no', 20)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance (BC style)
            $table->index('sell_to_customer_no');
            $table->index('bill_to_customer_no');
            $table->index('order_no');
            $table->index('posting_date');
            $table->index(['sell_to_customer_no', 'posting_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_shipment_headers');
    }
};
