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
        Schema::create('vendor_posting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('description');

            // Control accounts
            $table->foreignId('payables_account_id')
                ->constrained('chart_of_accounts'); // A/P
            $table->foreignId('payment_disc_debit_account_id')
                ->nullable()
                ->constrained('chart_of_accounts');
            $table->foreignId('payment_disc_credit_account_id')
                ->nullable()
                ->constrained('chart_of_accounts');
            $table->foreignId('invoice_rounding_account_id')
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
        Schema::dropIfExists('vendor_posting_groups');
    }
};
