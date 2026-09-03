<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('general_ledger_setup') || Schema::hasColumn('general_ledger_setup', 'opening_balance_equity_account_id')) {
            return;
        }

        Schema::table('general_ledger_setup', function (Blueprint $table): void {
            $table->foreignId('opening_balance_equity_account_id')
                ->nullable()
                ->after('default_expense_offset_account_id')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('general_ledger_setup') || ! Schema::hasColumn('general_ledger_setup', 'opening_balance_equity_account_id')) {
            return;
        }

        Schema::table('general_ledger_setup', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opening_balance_equity_account_id');
        });
    }
};
