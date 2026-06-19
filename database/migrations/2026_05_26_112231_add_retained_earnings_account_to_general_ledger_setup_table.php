<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_ledger_setup', function (Blueprint $table) {
            $table->foreignId('retained_earnings_account_id')
                ->nullable()
                ->after('allow_posting_to')
                ->constrained('chart_of_accounts');
        });
    }

    public function down(): void
    {
        Schema::table('general_ledger_setup', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retained_earnings_account_id');
        });
    }
};
