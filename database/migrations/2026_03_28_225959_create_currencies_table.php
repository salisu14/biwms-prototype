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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();

            // BC-standard currency code (ISO 4217)
            $table->string('code', 10)->unique(); // USD, EUR, GBP, etc.
            $table->string('description', 50);
            $table->string('symbol', 5)->nullable(); // $, €, £

            // Rounding rules (BC: Amount Decimal Places)
            $table->integer('decimal_places')->default(2); // 0, 2, 3, 5
            $table->string('rounding_method', 20)->default('nearest'); // CurrencyRoundingMethod
            $table->decimal('amount_rounding_precision', 18, 4)->default(0.01);
            $table->decimal('unit_amount_rounding_precision', 18, 4)->default(0.00001);

            // Exchange rate defaults
            $table->decimal('exchange_rate', 18, 6)->default(1); // Currency per unit of LCY
            $table->date('exchange_rate_date')->nullable();
            $table->string('exchange_rate_type', 20)->default('spot'); // CurrencyExchangeRateType

            // Realized gain/loss accounts (BC: Unrealized Gains/Losses Accounts)
            $table->foreignId('realized_gains_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('realized_losses_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('unrealized_gains_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('unrealized_losses_account_id')->nullable()->constrained('chart_of_accounts');

            // Payment tolerance (BC: Payment Tolerance %)
            $table->decimal('payment_tolerance_percent', 5, 2)->default(0);
            $table->decimal('max_payment_tolerance_amount', 18, 4)->nullable();

            // Invoice rounding (BC: Invoice Rounding Precision)
            $table->boolean('invoice_rounding')->default(false);
            $table->decimal('invoice_rounding_precision', 18, 4)->nullable();
            $table->foreignId('invoice_rounding_account_id')->nullable()->constrained('chart_of_accounts');

            // General Ledger accounts for currency
            $table->foreignId('receivables_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('payables_account_id')->nullable()->constrained('chart_of_accounts');

            // Reporting
            $table->string('reporting_currency_code', 10)->nullable(); // For consolidated reporting

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_lcy')->default(false); // Local Currency (base)

            // ISO standards
            $table->string('iso_numeric_code', 3)->nullable(); // 840, 978, etc.
            $table->string('iso_country_code', 2)->nullable(); // US, EU, GB

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('is_lcy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
