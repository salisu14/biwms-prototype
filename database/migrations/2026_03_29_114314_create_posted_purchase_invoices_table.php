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
        Schema::create('posted_purchase_invoices', function (Blueprint $table) {
            $table->id();

            // Document Identification
            $table->string('document_number', 20)->unique();
            $table->string('external_document_number', 50)->nullable(); // Vendor's invoice number

            // Source Document (links back to original order)
            $table->unsignedBigInteger('order_id')->nullable(); // PurchaseOrder ID
            $table->string('order_number', 20)->nullable(); // Snapshot of PO number

            // Vendor Information (snapshot at posting time)
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('vendor_name', 100);
            $table->string('vendor_address', 200)->nullable();

            // Posting Groups (copied from PO/Vendor at posting)
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups');
            $table->foreignId('vendor_posting_group_id')
                ->nullable()
                ->constrained('vendor_posting_groups');
            $table->string('vat_bus_posting_group', 20)->nullable();

            // Location/Warehouse
            $table->foreignId('location_id')->nullable()->constrained('locations');

            // Dates
            $table->date('posting_date'); // When posted to G/L
            $table->date('document_date'); // Invoice date from vendor
            $table->date('due_date'); // When payment is due
            $table->date('vat_date')->nullable(); // For VAT reporting

            // Amounts
            $table->decimal('total_amount', 15, 4)->default(0); // Net amount
            $table->decimal('total_vat', 15, 4)->default(0); // VAT amount
            $table->decimal('grand_total', 15, 4)->default(0); // Total payable

            // Currency
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('currency_factor', 15, 6)->default(1); // Exchange rate

            // Payment Status
            $table->decimal('amount_paid', 15, 4)->default(0);
            $table->decimal('remaining_amount', 15, 4)->default(0);
            $table->boolean('paid_in_full')->default(false);
            $table->timestamp('paid_in_full_date')->nullable();

            // Posting Information
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('posted_at');

            // Cancellation (if invoice is reversed)
            $table->boolean('cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('cancellation_reason', 200)->nullable();
            $table->string('corrective_document_number', 20)->nullable(); // Credit memo that reverses this

            // Dimensions for reporting
            $table->json('dimensions')->nullable();

            $table->timestamps();

            // Indexes for reporting and lookups
            $table->index(['vendor_id', 'posting_date']);
            $table->index(['order_id', 'posting_date']);
            $table->index(['posting_date', 'document_number']);
            $table->index(['paid_in_full', 'due_date']); // For payment reminders
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posted_purchase_invoices');
    }
};
