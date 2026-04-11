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
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->decimal('debit_amount_lcy', 20, 4)->nullable()->after('debit_amount');
            $table->decimal('credit_amount_lcy', 20, 4)->nullable()->after('credit_amount');
            $table->decimal('amount_lcy', 20, 4)->nullable()->after('amount');
            $table->foreignId('currency_id')->nullable()->after('amount_lcy')->constrained('currencies');
            $table->decimal('exchange_rate', 20, 6)->nullable()->after('currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->dropColumn(['debit_amount_lcy', 'credit_amount_lcy', 'amount_lcy', 'currency_id', 'exchange_rate']);
        });
    }
};
