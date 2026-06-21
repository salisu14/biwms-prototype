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
        Schema::create('blanket_orders', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 20)->unique();
            $table->string('external_document_no', 35)->nullable();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('document_type', 20)->default('BLANKET_ORDER');
            $table->string('status', 20)->default('OPEN'); // OPEN, ACTIVE, EXPIRED, CANCELLED, COMPLETED
            $table->date('posting_date')->nullable();
            $table->date('document_date')->nullable();
            $table->date('order_date')->nullable();
            $table->date('starting_date')->nullable();
            $table->date('ending_date')->nullable();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsibility_center', 10)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('project_code', 20)->nullable();
            $table->string('department_code', 20)->nullable();
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();
            $table->foreignId('dimension_set_id')->nullable();
            $table->string('vendor_order_no', 35)->nullable();
            $table->string('purchase_order_no', 20)->nullable();
            $table->string('order_address_code', 10)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->boolean('prices_including_vat')->default(false);
            $table->string('payment_terms_code', 10)->nullable();
            $table->string('payment_method_code', 10)->nullable();
            $table->string('transaction_type', 10)->nullable();
            $table->string('transaction_specification', 10)->nullable();
            $table->string('transport_method', 10)->nullable();
            $table->string('entry_point', 10)->nullable();
            $table->string('area', 10)->nullable();
            $table->string('language_code', 10)->nullable();
            $table->string('format_region', 20)->nullable();

            // Buy-from Address
            $table->string('buy_from_vendor_name', 100)->nullable();
            $table->string('buy_from_address', 100)->nullable();
            $table->string('buy_from_address_2', 50)->nullable();
            $table->string('buy_from_city', 30)->nullable();
            $table->string('buy_from_post_code', 20)->nullable();
            $table->string('buy_from_county', 30)->nullable();
            $table->string('buy_from_country_region_code', 10)->nullable();
            $table->string('buy_from_contact', 100)->nullable();

            // Pay-to Address
            $table->string('pay_to_vendor_no', 20)->nullable();
            $table->string('pay_to_name', 100)->nullable();
            $table->string('pay_to_address', 100)->nullable();
            $table->string('pay_to_address_2', 50)->nullable();
            $table->string('pay_to_city', 30)->nullable();
            $table->string('pay_to_post_code', 20)->nullable();
            $table->string('pay_to_county', 30)->nullable();
            $table->string('pay_to_country_region_code', 10)->nullable();
            $table->string('pay_to_contact', 100)->nullable();

            // Ship-to Address
            $table->string('ship_to_code', 10)->nullable();
            $table->string('ship_to_name', 100)->nullable();
            $table->string('ship_to_address', 100)->nullable();
            $table->string('ship_to_address_2', 50)->nullable();
            $table->string('ship_to_city', 30)->nullable();
            $table->string('ship_to_post_code', 20)->nullable();
            $table->string('ship_to_county', 30)->nullable();
            $table->string('ship_to_country_region_code', 10)->nullable();
            $table->string('ship_to_contact', 100)->nullable();

            $table->string('location_code', 10)->nullable();
            $table->string('shipment_method_code', 10)->nullable();
            $table->string('shipping_agent_code', 10)->nullable();
            $table->string('shipping_agent_service_code', 10)->nullable();
            $table->string('package_tracking_no', 30)->nullable();
            $table->string('invoice_disc_code', 20)->nullable();
            $table->date('requested_receipt_date')->nullable();
            $table->date('promised_receipt_date')->nullable();
            $table->string('quote_no', 20)->nullable();
            $table->text('comment')->nullable();
            $table->boolean('released')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('document_number');
            $table->index('vendor_id');
            $table->index('status');
            $table->index('released');
            $table->index(['starting_date', 'ending_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blanket_orders');
    }
};
