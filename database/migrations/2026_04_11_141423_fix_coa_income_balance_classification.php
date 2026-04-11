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
        // Set Income Statement for Revenue, COGS, Expense
        DB::table('chart_of_accounts')
            ->whereIn('account_type', ['REVENUE', 'COGS', 'EXPENSE'])
            ->update(['income_balance' => 'Income Statement']);

        // Set Balance Sheet for Asset, Liability, Equity
        DB::table('chart_of_accounts')
            ->whereIn('account_type', ['ASSET', 'LIABILITY', 'EQUITY'])
            ->update(['income_balance' => 'Balance Sheet']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to reverse without knowing previous state, 
        // but default is Balance Sheet anyway.
    }
};
