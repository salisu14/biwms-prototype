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
        Schema::create('customer_posting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('description');

            // Control accounts
            $table->foreignId('receivables_account_id')
                ->constrained('chart_of_accounts'); // A/R
            $table->foreignId('payment_disc_debit_account_id')
                ->nullable()
                ->constrained('chart_of_accounts');
            $table->foreignId('payment_disc_credit_account_id')
                ->nullable()
                ->constrained('chart_of_accounts');
            $table->foreignId('invoice_rounding_account_id')
                ->nullable()
                ->constrained('chart_of_accounts');
            $table->foreignId('debit_rounding_account_id')
                ->nullable()
                ->constrained('chart_of_accounts');
            $table->foreignId('credit_rounding_account_id')
                ->nullable()
                ->constrained('chart_of_accounts');

            $table->boolean('blocked')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_posting_groups');
    }
};
