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
        Schema::create('posted_sales_credit_memos', function (Blueprint $table) {
            $table->id();

            // Document Identification
            $table->string('document_number', 20)->unique();
            $table->string('external_document_number', 50)->nullable(); // Customer's return authorization

            // Source Document (the invoice being corrected)
            $table->unsignedBigInteger('corrected_invoice_id')->nullable();
            $table->string('corrected_invoice_number', 20)->nullable();

            // Original Order Reference
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('order_number', 20)->nullable();

            // Customer Information (snapshot)
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('customer_name', 100);
            $table->string('customer_address', 200)->nullable();
            $table->string('ship_to_name', 100)->nullable();
            $table->string('ship_to_address', 200)->nullable();

            // Posting Groups (copied from original invoice)
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups');
            $table->foreignId('customer_posting_group_id')
                ->nullable()
                ->constrained('customer_posting_groups');
            $table->string('vat_bus_posting_group', 20)->nullable();

            // Location/Warehouse (for returns)
            $table->foreignId('location_id')->nullable()->constrained('locations');

            // Return Information
            $table->enum('credit_memo_type', [
                'RETURN',           // Physical return of goods
                'ALLOWANCE',        // Price allowance without return
                'CORRECTION',       // Invoice error correction
                'WRITE_OFF',        // Bad debt/write-off
                'REBATE',           // Volume rebate
            ])->default('RETURN');

            $table->string('return_reason_code', 20)->nullable();
            $table->text('return_reason_comment')->nullable();

            // Dates
            $table->date('posting_date');
            $table->date('document_date');
            $table->date('vat_date')->nullable();

            // Amounts (typically negative)
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('line_discount_total', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0); // Net amount (negative)
            $table->decimal('total_vat', 15, 4)->default(0);    // VAT (negative)
            $table->decimal('grand_total', 15, 4)->default(0);  // Total credit (negative)

            // Currency
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('currency_factor', 15, 6)->default(1);

            // Application Status (how much applied to invoices)
            $table->decimal('amount_applied', 15, 4)->default(0);
            $table->decimal('remaining_amount', 15, 4)->default(0);
            $table->boolean('fully_applied')->default(false);
            $table->timestamp('fully_applied_date')->nullable();

            // Refund Information
            $table->boolean('refunded')->default(false);
            $table->decimal('refund_amount', 15, 4)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reference', 50)->nullable(); // Check number, transaction ID

            // Posting Information
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->foreignId('salesperson_id')->nullable()->constrained('users');

            // Correction (if this CM is itself corrected)
            $table->boolean('corrected')->default(false);
            $table->timestamp('corrected_at')->nullable();
            $table->string('correcting_document_number', 20)->nullable();

            // Dimensions
            $table->json('dimensions')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['customer_id', 'posting_date']);
            $table->index(['corrected_invoice_id', 'posting_date']);
            $table->index(['posting_date', 'document_number']);
            $table->index(['fully_applied', 'remaining_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posted_sales_credit_memos');
    }
};
