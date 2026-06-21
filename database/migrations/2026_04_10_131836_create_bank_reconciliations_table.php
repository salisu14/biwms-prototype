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
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bank_account_id')->constrained('bank_accounts');
            $table->string('statement_no', 20);
            $table->date('statement_date');
            $table->decimal('statement_ending_balance', 18, 4);

            $table->decimal('bank_balance_at_reconciliation', 18, 4);
            $table->decimal('uncleared_deposits', 18, 4)->default(0);
            $table->decimal('uncleared_withdrawals', 18, 4)->default(0);
            $table->decimal('adjusted_bank_balance', 18, 4);

            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users');

            $table->timestamps();

            $table->unique(['bank_account_id', 'statement_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
