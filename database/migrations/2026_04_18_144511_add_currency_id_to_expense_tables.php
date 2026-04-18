<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_transactions', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->constrained('currencies');
        });

        Schema::table('expense_budgets', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->constrained('currencies');
        });

        // Data migration: Populate currency_id based on currency_code
        $transactions = DB::table('expense_transactions')->whereNotNull('currency_code')->get();
        foreach ($transactions as $transaction) {
            $currency = DB::table('currencies')->where('code', $transaction->currency_code)->first();
            if ($currency) {
                DB::table('expense_transactions')
                    ->where('id', $transaction->id)
                    ->update(['currency_id' => $currency->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });

        Schema::table('expense_budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });
    }
};
