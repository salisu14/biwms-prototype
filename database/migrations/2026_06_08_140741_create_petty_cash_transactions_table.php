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
        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_fund_id')->constrained();
            $table->foreignId('petty_cash_voucher_id')->nullable()->constrained();
            $table->string('transaction_number')->unique();
            $table->date('date');
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->decimal('running_balance', 15, 2);
            $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts');
            $table->string('description');
            $table->string('reference_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_transactions');
    }
};
