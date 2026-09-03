<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subledger_opening_balances')) {
            return;
        }

        Schema::create('subledger_opening_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses');
            $table->string('party_type', 20);
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->string('document_number', 30)->unique();
            $table->string('external_document_number', 100)->nullable();
            $table->string('original_document_type', 40)->default('OPENING_BALANCE');
            $table->date('posting_date');
            $table->date('document_date');
            $table->date('due_date')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('currency_code', 3);
            $table->decimal('original_amount', 20, 6);
            $table->decimal('currency_factor', 20, 8)->default(1);
            $table->decimal('amount_lcy', 20, 4);
            $table->decimal('remaining_amount', 20, 6);
            $table->decimal('remaining_amount_lcy', 20, 4);
            $table->foreignId('control_account_id')->constrained('chart_of_accounts');
            $table->foreignId('opening_equity_account_id')->constrained('chart_of_accounts');
            $table->foreignId('general_business_posting_group_id')->nullable()->constrained('general_business_posting_groups');
            $table->foreignId('customer_posting_group_id')->nullable()->constrained('customer_posting_groups');
            $table->foreignId('vendor_posting_group_id')->nullable()->constrained('vendor_posting_groups');
            $table->string('description');
            $table->string('source_type', 255);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('dimensions')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('subledger_opening_balances')->nullOnDelete();
            $table->foreignId('customer_ledger_entry_id')->nullable()->constrained('customer_ledger_entries')->nullOnDelete();
            $table->foreignId('vendor_ledger_entry_id')->nullable()->constrained('vendor_ledger_entries')->nullOnDelete();
            $table->foreignId('posting_transaction_id')->nullable()->constrained('posting_transactions')->nullOnDelete();
            $table->string('idempotency_key', 150)->unique();
            $table->timestamps();

            $table->index(['business_id', 'party_type', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subledger_opening_balances');
    }
};
