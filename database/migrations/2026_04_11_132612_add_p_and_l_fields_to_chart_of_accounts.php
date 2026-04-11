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
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->string('income_balance')->default('Balance Sheet')->comment('Balance Sheet or Income Statement');
            $table->string('gl_account_type')->default('Posting');
            $table->string('totaling')->nullable()->comment('Account range for total accounts (e.g., 6100..6199)');
            $table->integer('indentation')->default(0);
            $table->boolean('bold')->default(false);
            $table->boolean('show_opposite_sign')->default(false);
            $table->boolean('new_page')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'income_balance',
                'gl_account_type',
                'totaling',
                'indentation',
                'bold',
                'show_opposite_sign',
                'new_page',
            ]);
        });
    }
};
