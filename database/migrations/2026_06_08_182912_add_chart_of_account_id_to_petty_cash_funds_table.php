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
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            // Add the column (nullable so existing records don't fail)
            $table->foreignId('chart_of_account_id')
                ->nullable()
                ->after('id') // Place it wherever makes sense for your schema
                ->constrained('chart_of_accounts')
                ->nullOnDelete(); // If the account is deleted, set this to null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            // Must drop the foreign key first, then the column
            $table->dropForeign(['chart_of_account_id']);
            $table->dropColumn('chart_of_account_id');
        });
    }
};
