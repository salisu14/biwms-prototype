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
        Schema::table('general_posting_setups', function (Blueprint $table) {
            $table->foreignId('purchase_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('purchase_credit_memo_account_id')->nullable()->constrained('chart_of_accounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_posting_setups', function (Blueprint $table) {
            $table->dropColumn(['purchase_account_id', 'purchase_credit_memo_account_id']);
        });
    }
};
