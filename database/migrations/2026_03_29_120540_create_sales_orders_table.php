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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();

            // Document Identification
            $table->string('order_number', 20)->unique();
            $table->string('external_document_number', 50)->nullable(); // Customer PO number

            // Order Type
            $table->enum('order_type', [
                'SALES_ORDER',
                'RETURN_ORDER',
                'REPLACEMENT',
                'CONTRACT',
            ])->default('SALES_ORDER');

            // Customer Information
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('customer_name', 100);
            $table->string('customer_address', 200)->nullable();
            $table->string('ship_to_name', 100)->nullable();
            $table->string('ship_to_address', 200)->nullable();

            // Posting Groups (copied from customer)
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups');
            $table->foreignId('customer_posting_group_id')
                ->nullable()
                ->constrained('customer_posting_groups');
            $table->string('vat_bus_posting_group', 20)->nullable();

            // Pricing Group (for price determination)
            $table->foreignId('pricing_group_id')
                ->nullable()
                ->constrained('pricing_groups');

            // Location/Warehouse
            $table->foreignId('location_id')->nullable()->constrained('locations');

            // Shipping
            $table->string('shipping_agent_code', 20)->nullable();
            $table->string('shipping_agent_service_code', 20)->nullable();
            $table->enum('shipping_method', [
                'GROUND',
                'EXPRESS',
                'OVERNIGHT',
                'PICKUP',
                'FREIGHT',
            ])->nullable();

            // Dates
            $table->date('order_date');
            $table->date('posting_date')->nullable();
            $table->date('requested_delivery_date')->nullable();
            $table->date('promised_delivery_date')->nullable();
            $table->date('shipment_date')->nullable(); // Planned ship date

            // Payment Terms
            $table->string('payment_terms_code', 20)->nullable();
            $table->string('payment_method_code', 20)->nullable();

            // Pricing and Totals
            $table->decimal('subtotal', 15, 4)->default(0); // Before discounts
            $table->decimal('line_discount_total', 15, 4)->default(0);
            $table->decimal('invoice_discount_percent', 5, 2)->nullable();
            $table->decimal('invoice_discount_amount', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0); // After discounts, before VAT
            $table->decimal('total_vat', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);

            // Currency
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('currency_factor', 15, 6)->default(1);

            // Status
            $table->enum('status', [
                'DRAFT',
                'PENDING_APPROVAL',
                'APPROVED',
                'RELEASED',
                'PICKING',
                'PACKED',
                'SHIPPED',
                'INVOICED',
                'PARTIALLY_INVOICED',
                'CLOSED',
                'CANCELLED',
            ])->default('DRAFT');

            // Tracking Progress
            $table->decimal('quantity_shipped', 15, 4)->default(0);
            $table->decimal('quantity_invoiced', 15, 4)->default(0);
            $table->boolean('fully_shipped')->default(false);
            $table->boolean('fully_invoiced')->default(false);

            // Assignment
            $table->foreignId('salesperson_id')->nullable()->constrained('users');
            $table->foreignId('assigned_warehouse_worker_id')->nullable()->constrained('users');

            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            // Creation
            $table->foreignId('created_by')->constrained('users');

            // Cancellation
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('cancellation_reason', 200)->nullable();

            // Dimensions for reporting
            $table->json('dimensions')->nullable();

            // Notes
            $table->text('internal_comment')->nullable();
            $table->text('customer_comment')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['customer_id', 'order_date']);
            $table->index(['status', 'location_id']);
            $table->index(['shipment_date', 'status']);
            $table->index(['external_document_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
