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
        Schema::create('vendor_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->string('external_document_no')->nullable(); // Vendor's invoice number

            // Vendor reference
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('vendor_invoice_no');
            $table->date('vendor_invoice_date');

            // Document type
            $table->string('document_type')->default('INVOICE'); // INVOICE, CREDIT_MEMO, DEBIT_MEMO, INTEREST

            // Status workflow
            $table->string('status')->default('OPEN'); // OPEN, APPROVED, POSTED, PAID, CANCELLED, REJECTED

            // Amounts
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('amount_including_tax', 15, 2)->default(0);

            // Currency
            $table->string('currency_code')->default('USD');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('amount_lcy', 15, 2)->default(0); // Local currency

            // Dates
            $table->date('posting_date');
            $table->date('due_date');
            $table->date('receipt_date')->nullable(); // When goods/services received

            // Payment terms
            $table->string('payment_terms_code')->nullable();
            $table->string('payment_method_code')->nullable();

            // GL accounts
            $table->foreignId('payable_gl_account_id')->constrained('gl_accounts');
            $table->foreignId('expense_gl_account_id')->nullable()->constrained('gl_accounts');

            // Source document linking (for 3-way matching)
            $table->string('source_document_type')->nullable(); // PURCHASE_ORDER, PURCHASE_RECEIPT, BLANKET_ORDER
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->string('source_document_no')->nullable();

            // Department/Dimensions
            $table->string('shortcut_dimension_1_code')->nullable();
            $table->string('shortcut_dimension_2_code')->nullable();
            $table->unsignedBigInteger('dimension_set_id')->nullable();

            // Approval workflow
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            // Posting
            $table->boolean('posted')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');

            // Payment tracking
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->date('last_payment_date')->nullable();

            // CapEx project link (for capitalizable invoices)
            $table->foreignId('capex_project_id')->nullable()->constrained('capex_projects');
            $table->boolean('capitalized')->default(false);

            // Notes
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('last_modified_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['vendor_id', 'status']);
            $table->index(['posting_date', 'document_type']);
            $table->index(['source_document_type', 'source_document_id']);
            $table->index(['capex_project_id', 'capitalized']);
            $table->index(['external_document_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_invoices');
    }
};
