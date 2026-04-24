<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('expense_transactions')
            ->whereNotNull('currency_code')
            ->orderBy('id')
            ->chunk(100, function ($transactions) {
                foreach ($transactions as $transaction) {
                    $currency = DB::table('currencies')
                        ->where('code', $transaction->currency_code)
                        ->first();

                    if ($currency) {
                        DB::table('expense_transactions')
                            ->where('id', $transaction->id)
                            ->update([
                                'currency_id' => $currency->id
                            ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
