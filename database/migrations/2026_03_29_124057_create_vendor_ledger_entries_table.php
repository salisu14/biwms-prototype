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
        Schema::create('vendor_ledger_entries', function (Blueprint $table) {
            $table->id();

            // Entry Number (sequential per vendor)
            $table->unsignedBigInteger('entry_number');

            // Vendor
            $table->foreignId('vendor_id')->constrained('vendors');

            // Document Reference
            $table->enum('document_type', [
                'PURCHASE_INVOICE',
                'PURCHASE_CREDIT_MEMO',
                'PAYMENT',
                'REFUND',
                'CREDIT_MEMO_APPLICATION',
                'FINANCE_CHARGE',
                'ADJUSTMENT',
                'WRITE_OFF',
                'PAYMENT_DISCOUNT',
                'BANK_TRANSFER',
            ]);

            $table->string('document_number', 20);
            $table->string('external_document_number', 50)->nullable(); // Vendor's invoice number, check number

            // Description
            $table->string('description');
            $table->text('comment')->nullable();

            // Posting Date (for G/L and reporting)
            $table->date('posting_date');
            $table->date('document_date'); // Original document date
            $table->date('due_date')->nullable();

            // Amounts (from vendor perspective - opposite of customer)
            // Debit = We owe vendor (Invoice) - INCREASES AP
            // Credit = We paid vendor (Payment) - DECREASES AP
            $table->decimal('debit_amount', 15, 4)->default(0);   // Invoice amounts (we owe)
            $table->decimal('credit_amount', 15, 4)->default(0);  // Payments, credit memos (we paid)
            $table->decimal('amount', 15, 4); // Debit - Credit (running balance calc)

            // Running Balance (after this entry) - Positive = we owe vendor (AP)
            $table->decimal('running_balance', 15, 4)->default(0);

            // Remaining Amount (for open entries)
            $table->decimal('remaining_amount', 15, 4)->default(0);
            $table->boolean('open')->default(true); // Not fully applied/paid

            // Application Information (for payments/credit memos)
            $table->json('applied_to_entries')->nullable(); // ['entry_id' => 1, 'amount' => 100.00]
            $table->boolean('fully_applied')->default(false);

            // Currency
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('original_debit_amount', 15, 4)->default(0); // In original currency
            $table->decimal('original_credit_amount', 15, 4)->default(0);
            $table->decimal('currency_factor', 15, 6)->default(1);

            // Posting Groups (snapshot)
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups');
            $table->foreignId('vendor_posting_group_id')
                ->nullable()
                ->constrained('vendor_posting_groups');

            // Related G/L Entry
            $table->unsignedBigInteger('gl_entry_id')->nullable();

            // Source Document Reference
            $table->unsignedBigInteger('source_id')->nullable(); // PurchaseInvoice, PostedPurchaseCreditMemo, Payment, etc.
            $table->string('source_type', 50)->nullable(); // Model class name

            // User
            $table->foreignId('created_by')->constrained('users');

            // Reversal Information
            $table->boolean('reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->string('reversal_entry_number', 20)->nullable(); // Correcting entry

            // Payment Terms (for calculating due dates, discounts)
            $table->string('payment_terms_code', 20)->nullable();
            $table->decimal('payment_discount_percent', 5, 2)->nullable();
            $table->date('payment_discount_due_date')->nullable();

            // Retainage/Holdback (for construction/industry)
            $table->decimal('retainage_amount', 15, 4)->nullable();
            $table->date('retainage_due_date')->nullable();

            // Dimensions for reporting
            $table->json('dimensions')->nullable();

            $table->timestamps();

            // Critical Indexes
            $table->unique(['vendor_id', 'entry_number']);
            $table->index(['vendor_id', 'posting_date', 'entry_number']);
            $table->index(['document_type', 'document_number']);
            $table->index(['open', 'due_date']); // For aging reports
            $table->index(['vendor_id', 'open']); // For balance calculations
            $table->index(['payment_discount_due_date', 'open']); // For discount opportunities
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_ledger_entries');
    }
};
