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
        // 1. Extend assets table
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('currency_code')->constrained('currencies');
        });

        // 2. Extend fa_posting_groups table
        Schema::table('fa_posting_groups', function (Blueprint $table) {
            $table->foreignId('appreciation_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('revaluation_gain_account_id')->nullable()->constrained('chart_of_accounts');
        });

        // 3. Extend asset_ledger_entries table
        Schema::table('asset_ledger_entries', function (Blueprint $table) {
            $table->decimal('amount_lcy', 20, 4)->nullable()->after('amount');
            $table->foreignId('currency_id')->nullable()->after('amount_lcy')->constrained('currencies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_ledger_entries', function (Blueprint $table) {
            $table->dropColumn(['amount_lcy', 'currency_id']);
        });

        Schema::table('fa_posting_groups', function (Blueprint $table) {
            $table->dropColumn(['appreciation_account_id', 'revaluation_gain_account_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('currency_id');
        });
    }
};
