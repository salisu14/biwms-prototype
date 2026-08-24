<?php

declare(strict_types=1);

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
        Schema::create('customer_ledger_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('source_customer_ledger_entry_id')
                ->constrained('customer_ledger_entries')
                ->cascadeOnDelete();
            $table->foreignId('target_customer_ledger_entry_id')
                ->constrained('customer_ledger_entries')
                ->cascadeOnDelete();
            $table->foreignId('source_posted_sales_credit_memo_id')
                ->constrained('posted_sales_credit_memos')
                ->cascadeOnDelete();
            $table->foreignId('target_posted_sales_invoice_id')
                ->constrained('posted_sales_invoices')
                ->cascadeOnDelete();
            $table->decimal('amount', 15, 4);
            $table->string('currency_code', 3);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('source_remaining_before', 15, 4);
            $table->decimal('source_remaining_after', 15, 4);
            $table->decimal('target_remaining_before', 15, 4);
            $table->decimal('target_remaining_after', 15, 4);
            $table->timestamp('applied_at');
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reference', 80)->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'applied_at']);
            $table->index(['source_posted_sales_credit_memo_id', 'reversed'], 'cust_ledger_apps_source_credit_reversed_idx');
            $table->index(['target_posted_sales_invoice_id', 'reversed'], 'cust_ledger_apps_target_invoice_reversed_idx');
            $table->index(['source_customer_ledger_entry_id', 'target_customer_ledger_entry_id'], 'cust_ledger_apps_source_target_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_applications');
    }
};
