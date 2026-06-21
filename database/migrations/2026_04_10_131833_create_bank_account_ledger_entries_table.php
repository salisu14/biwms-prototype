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
        Schema::create('bank_account_ledger_entries', function (Blueprint $table) {
            $table->id();

            // Entry identification (BC: Entry No.)
            $table->integer('entry_number')->unique();

            // Bank account reference
            $table->foreignId('bank_account_id')->constrained('bank_accounts');
            $table->string('bank_account_no', 20)->nullable(); // Denormalized for history

            // Posting information
            $table->date('posting_date');
            $table->date('document_date')->nullable();
            $table->date('due_date')->nullable();

            // Document reference
            $table->string('document_type', 30)->nullable(); // 'payment', 'refund', 'transfer'
            $table->string('document_no', 20);
            $table->string('external_document_no', 35)->nullable(); // Bank reference

            // Description
            $table->text('description');
            $table->text('description_2')->nullable();

            // Transaction type
            $table->string('entry_type', 30); // BankAccountLedgerEntryType
            $table->string('check_type', 30)->nullable(); // CheckType
            $table->string('check_no', 20)->nullable();
            $table->date('check_date')->nullable();

            // Amounts (BC: Amount, Amount (LCY))
            $table->decimal('amount', 18, 4);
            $table->decimal('amount_lcy', 18, 4);
            $table->decimal('debit_amount', 18, 4)->default(0);
            $table->decimal('credit_amount', 18, 4)->default(0);

            // Currency
            $table->string('currency_code', 10)->nullable();
            $table->decimal('currency_factor', 18, 6)->default(1);

            // Running balance (BC: Balance)
            $table->decimal('balance', 18, 4);
            $table->decimal('balance_lcy', 18, 4);

            // Reconciliation (BC: Open, Statement No., Statement Line No.)
            $table->string('status', 20)->default('open'); // BankAccountLedgerEntryStatus
            $table->boolean('open')->default(true);
            $table->string('statement_no', 20)->nullable();
            $table->integer('statement_line_no')->nullable();
            $table->date('statement_date')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users');

            // Related entries
            $table->foreignId('vendor_ledger_entry_id')->nullable()->constrained('vendor_ledger_entries');
            $table->foreignId('customer_ledger_entry_id')->nullable()->constrained('customer_ledger_entries');
            $table->foreignId('gl_entry_id')->nullable()->constrained('gl_entries');
            $table->foreignId('transfer_entry_id')->nullable()->constrained('bank_account_ledger_entries');

            // Source transaction
            $table->string('source_type', 30)->nullable(); // 'vendor', 'customer', 'employee', 'gl'
            $table->foreignId('source_id')->nullable();
            $table->string('source_no', 20)->nullable();

            // Dimensions
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();
            $table->json('dimensions')->nullable();

            // User tracking
            $table->foreignId('user_id')->constrained('users');
            $table->string('journal_batch_name', 20)->nullable();
            $table->string('journal_template_name', 20)->nullable();
            $table->integer('journal_line_no')->nullable();

            // Void information
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->string('void_reason', 255)->nullable();

            // Metadata
            $table->text('comment')->nullable();
            $table->json('additional_fields')->nullable(); // Extensibility

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['bank_account_id', 'posting_date']);
            $table->index(['bank_account_id', 'open']);
            $table->index(['bank_account_id', 'status']);
            $table->index(['entry_type', 'document_no']);
            $table->index('statement_no');
            $table->index(['posting_date', 'document_no']);
            $table->index('check_no');
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_account_ledger_entries');
    }
};
