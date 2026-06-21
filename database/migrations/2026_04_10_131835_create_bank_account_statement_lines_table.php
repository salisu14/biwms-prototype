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
        Schema::create('bank_account_statement_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bank_account_id')->constrained('bank_accounts');
            $table->string('statement_no', 20);
            $table->integer('statement_line_no');

            $table->date('transaction_date');
            $table->string('description');
            $table->string('reference_no', 50)->nullable();

            $table->decimal('statement_amount', 18, 4);
            $table->decimal('debit_amount', 18, 4)->default(0);
            $table->decimal('credit_amount', 18, 4)->default(0);

            // Reconciliation link
            $table->foreignId('bank_account_ledger_entry_id')->nullable()
                ->constrained('bank_account_ledger_entries');
            $table->boolean('reconciled')->default(false);
            $table->decimal('difference', 18, 4)->default(0);

            $table->timestamps();

            $table->unique(['bank_account_id', 'statement_no', 'statement_line_no']);
            $table->index(['bank_account_id', 'statement_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_account_statement_lines');
    }
};
