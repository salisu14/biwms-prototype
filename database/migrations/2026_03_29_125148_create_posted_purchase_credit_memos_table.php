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
        Schema::create('posted_purchase_credit_memos', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Document Identification
            $table->string('document_number')->unique();
            $table->string('external_document_number')->nullable();
            $table->string('vendor_invoice_number')->nullable();

            // Vendor Information (denormalized for historical accuracy)
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('vendor_name');
            $table->text('vendor_address')->nullable();
            $table->string('vendor_city')->nullable();
            $table->string('vendor_post_code')->nullable();
            $table->string('vendor_country')->nullable();
            $table->string('vendor_tax_registration_number')->nullable();

            // Posting Information
            $table->date('posting_date');
            $table->date('document_date');
            $table->date('due_date')->nullable();

            // WMS Posting Groups Setup References
            $table->foreignId('vendor_posting_group_id')
                ->nullable()
                ->constrained('vendor_posting_groups')
                ->comment('Specific posting group - maps to AP control account');
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups')
                ->comment('General posting group - categorizes vendor type');

            // Currency Information
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('currency_factor', 15, 6)->default(1);

            // Amounts
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);

            // Posting Status
            $table->boolean('posted')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');

            // Source Reference (links to unposted document)
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->string('source_document_type')->nullable();

            // Correction Reference
            $table->string('corrects_invoice_number')->nullable();
            $table->foreignId('corrects_invoice_id')
                ->nullable()
                ->constrained('purchase_invoices');

            // Payment Terms
            $table->string('payment_terms_code')->nullable();

            // Dimensions (JSON for flexibility)
            $table->json('dimensions')->nullable();

            // Reason and Description
            $table->string('reason_code')->nullable();
            $table->text('description')->nullable();

            // Location/Warehouse (for WMS integration)
            $table->string('location_code')->nullable();
            $table->string('warehouse_receipt_number')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['vendor_id', 'posting_date']);
            $table->index(['posted', 'posting_date']);
            $table->index(['vendor_posting_group_id']);
            $table->index(['general_business_posting_group_id']);
            $table->index(['corrects_invoice_id']);
            $table->index(['source_document_id', 'source_document_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posted_purchase_credit_memos');
    }
};
