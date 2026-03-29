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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('account_code', 20)->unique();
            $table->string('account_name');
            $table->string('bank_name', 100);
            $table->string('bank_branch', 100)->nullable();
            $table->string('account_number', 50);
            $table->string('routing_number', 20)->nullable();
            $table->string('swift_code', 20)->nullable(); // For international
            $table->string('iban', 34)->nullable(); // International

            // G/L Account Link
            $table->foreignId('gl_account_id')->constrained('chart_of_accounts');

            // Currency
            $table->string('currency_code', 3)->default('USD');

            // Account Type
            $table->enum('account_type', [
                'CHECKING',
                'SAVINGS',
                'MONEY_MARKET',
                'CERTIFICATE_OF_DEPOSIT',
                'FOREIGN_CURRENCY',
            ])->default('CHECKING');

            // Controls
            $table->decimal('current_balance', 15, 4)->default(0);
            $table->decimal('available_balance', 15, 4)->default(0);
            $table->date('last_reconciliation_date')->nullable();
            $table->decimal('last_reconciliation_balance', 15, 4)->nullable();

            // Check printing
            $table->string('next_check_number', 20)->nullable();
            $table->string('check_form_id', 20)->nullable();

            // Status
            $table->boolean('active')->default(true);
            $table->boolean('allow_payments')->default(true);
            $table->boolean('allow_receipts')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
