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
        Schema::create('posted_sales_invoices', function (Blueprint $table) {
            $table->id();

            // Document Identification
            $table->string('document_number', 20)->unique();
            $table->string('external_document_number', 50)->nullable();

            // Source Order
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('order_number', 20)->nullable();

            // Customer Information (snapshot)
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('customer_name', 100);
            $table->string('customer_address', 200)->nullable();
            $table->string('ship_to_name', 100)->nullable();
            $table->string('ship_to_address', 200)->nullable();

            // Posting Groups (snapshot)
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups');
            $table->foreignId('customer_posting_group_id')
                ->nullable()
                ->constrained('customer_posting_groups');
            $table->string('vat_bus_posting_group', 20)->nullable();

            // Location/Shipping
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->string('shipping_agent_code', 20)->nullable();

            // Dates
            $table->date('posting_date');
            $table->date('document_date');
            $table->date('due_date');
            $table->date('vat_date')->nullable();
            $table->date('shipment_date')->nullable();

            // Amounts
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('line_discount_total', 15, 4)->default(0);
            $table->decimal('invoice_discount_amount', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->decimal('total_vat', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);

            // Currency
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('currency_factor', 15, 6)->default(1);

            // Payment Status
            $table->decimal('amount_paid', 15, 4)->default(0);
            $table->decimal('remaining_amount', 15, 4)->default(0);
            $table->boolean('paid_in_full')->default(false);
            $table->timestamp('paid_in_full_date')->nullable();

            // Posting Information
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->foreignId('salesperson_id')->nullable()->constrained('users');

            // Cancellation
            $table->boolean('cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('corrective_document_number', 20)->nullable();

            // Dimensions
            $table->json('dimensions')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['customer_id', 'posting_date']);
            $table->index(['order_id', 'posting_date']);
            $table->index(['posting_date', 'document_number']);
            $table->index(['paid_in_full', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posted_sales_invoices');
    }
};
