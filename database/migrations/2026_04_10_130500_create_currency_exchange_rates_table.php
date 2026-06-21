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
        Schema::create('currency_exchange_rates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('currency_id')->constrained('currencies')->onDelete('cascade');
            $table->date('starting_date'); // Effective date
            $table->date('ending_date')->nullable(); // Null = current

            // Exchange rates (BC: Relational Exch. Rate Amount, Exchange Rate Amount)
            $table->decimal('exchange_rate_amount', 18, 6); // 1 USD = X LCY
            $table->decimal('relational_exch_rate_amount', 18, 6)->default(1); // Usually 1

            // Adjustment exchange rate (BC: Adjustment Exch. Rate)
            $table->decimal('adjustment_exch_rate_amount', 18, 6)->nullable();

            // Rate type
            $table->string('rate_type', 20)->default('spot'); // CurrencyExchangeRateType

            // Source tracking
            $table->string('source', 50)->nullable(); // 'ecb', 'bank_of_england', 'manual', 'service'
            $table->string('source_reference', 100)->nullable(); // API reference, file name

            // Status
            $table->boolean('is_current')->default(false);

            $table->timestamps();

            // Indexes for performance
            $table->unique(['currency_id', 'starting_date', 'rate_type']);
            $table->index(['currency_id', 'is_current']);
            $table->index('starting_date');

            // Ensure only one current rate per currency
            $table->index(['currency_id', 'is_current'], 'idx_currency_current');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_exchange_rates');
    }
};
