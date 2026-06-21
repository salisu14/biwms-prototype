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
        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();

            // Entry Number (sequential per customer)
            $table->unsignedBigInteger('entry_number');

            // Customer
            $table->foreignId('customer_id')->constrained('customers');

            // Document Reference
            $table->enum('document_type', [
                'SALES_INVOICE',
                'SALES_CREDIT_MEMO',
                'PAYMENT',
                'REFUND',
                'CREDIT_MEMO_APPLICATION',
                'FINANCE_CHARGE',
                'REMINDER',
                'BANK_TRANSFER',
                'CASH_RECEIPT',
                'ADJUSTMENT',
                'WRITE_OFF',
            ]);

            $table->string('document_number', 20);
            $table->string('external_document_number', 50)->nullable(); // Customer's check number, etc.

            // Description
            $table->string('description');
            $table->text('comment')->nullable();

            // Posting Date (for G/L and reporting)
            $table->date('posting_date');
            $table->date('document_date'); // Original document date
            $table->date('due_date')->nullable();

            // Amounts (from customer perspective)
            // Debit = Customer owes us (Invoice)
            // Credit = Customer paid us (Payment) or we owe them (Credit Memo)
            $table->decimal('debit_amount', 15, 4)->default(0);   // Invoice amounts
            $table->decimal('credit_amount', 15, 4)->default(0);  // Payments, credit memos
            $table->decimal('amount', 15, 4); // Debit - Credit (running balance calc)

            // Running Balance (after this entry)
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
            $table->foreignId('customer_posting_group_id')
                ->nullable()
                ->constrained('customer_posting_groups');

            // Related G/L Entry
            $table->unsignedBigInteger('gl_entry_id')->nullable();

            // Source Document Reference
            $table->unsignedBigInteger('source_id')->nullable(); // PostedSalesInvoice, PostedSalesCreditMemo, etc.
            $table->string('source_type', 50)->nullable(); // Model class name

            // User
            $table->foreignId('created_by')->constrained('users');

            // Reversal Information
            $table->boolean('reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->string('reversal_entry_number', 20)->nullable(); // Correcting entry

            // Dimensions for reporting
            $table->json('dimensions')->nullable();

            $table->timestamps();

            // Critical Indexes
            $table->unique(['customer_id', 'entry_number']);
            $table->index(['customer_id', 'posting_date', 'entry_number']);
            $table->index(['document_type', 'document_number']);
            $table->index(['open', 'due_date']); // For aging reports
            $table->index(['customer_id', 'open']); // For balance calculations
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
    }
};
