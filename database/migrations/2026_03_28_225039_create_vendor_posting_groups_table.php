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
            $table->string('payables_account', 20)->nullable(); // GL Account for payables
            $table->string('service_charge_acc', 20)->nullable();
            $table->string('payment_disc_debit_acc', 20)->nullable();
            $table->string('payment_disc_credit_acc', 20)->nullable();
            $table->string('invoice_rounding_account', 20)->nullable();
            $table->string('debit_curr_appl_acc', 20)->nullable();
            $table->string('credit_curr_appl_acc', 20)->nullable();
            $table->string('debit_appl_acc', 20)->nullable();
            $table->string('credit_appl_acc', 20)->nullable();
            $table->string('prepayment_account', 20)->nullable();
            $table->boolean('blocked')->default(false);

            $table->foreignId('payables_account_id')
                ->nullable()
                ->after('payables_account')
                ->constrained('chart_of_accounts');

            $table->foreignId('payment_disc_debit_account_id')
                ->nullable()
                ->after('payment_disc_debit_acc')
                ->constrained('chart_of_accounts');

            $table->foreignId('payment_disc_credit_account_id')
                ->nullable()
                ->after('payment_disc_credit_acc')
                ->constrained('chart_of_accounts');

            $table->foreignId('invoice_rounding_account_id')
                ->nullable()
                ->after('invoice_rounding_account')
                ->constrained('chart_of_accounts');

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
