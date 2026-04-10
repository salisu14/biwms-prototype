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
        Schema::create('currency_adjustment_ledger', function (Blueprint $table) {
            $table->id();

            $table->foreignId('currency_id')->constrained('currencies');
            $table->foreignId('adjustment_account_id')->constrained('chart_of_accounts');

            // Document reference
            $table->string('document_type', 30); // 'revaluation', 'payment', 'invoice'
            $table->string('document_no', 20);
            $table->date('posting_date');

            // Adjustment details
            $table->string('adjustment_type', 30); // CurrencyAdjustmentType
            $table->decimal('original_amount', 18, 4);
            $table->decimal('adjusted_amount', 18, 4);
            $table->decimal('adjustment_amount', 18, 4); // Gain or loss amount

            // Exchange rates
            $table->decimal('original_exch_rate', 18, 6);
            $table->decimal('new_exch_rate', 18, 6);

            // Ledger entry references
            $table->foreignId('vendor_ledger_entry_id')->nullable()->constrained('vendor_ledger_entries');
            $table->foreignId('customer_ledger_entry_id')->nullable()->constrained('customer_ledger_entries');
            $table->foreignId('bank_account_ledger_entry_id')->nullable()->constrained('bank_account_ledger_entries');
            $table->foreignId('gl_entry_id')->nullable()->constrained('gl_entries');

            $table->foreignId('created_by')->constrained('users');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['currency_id', 'posting_date']);
            $table->index(['document_type', 'document_no']);
            $table->index('adjustment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_adjustment_ledgers');
    }
};
