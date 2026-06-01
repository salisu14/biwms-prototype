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
        Schema::table('general_ledger_setup', function (Blueprint $table) {
            $table->foreignId('default_expense_offset_account_id')
                ->nullable()
                ->after('retained_earnings_account_id')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_ledger_setup', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_expense_offset_account_id');
        });
    }
};
