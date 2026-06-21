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
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_no', 20)->unique();
            $table->string('account_name', 100);
            $table->string('account_type', 50); // Posting, Heading, Total, Begin-Total, End-Total
            $table->string('account_category', 50)->nullable(); // Assets, Liabilities, Equity, Income, Cost of Goods Sold, Expense
            $table->foreignId('parent_account_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->string('balance_account_type', 20)->nullable();
            $table->string('income_balance', 20)->nullable(); // Balance Sheet, Income Statement
            $table->string('debit_credit', 10)->nullable(); // Both, Debit, Credit
            $table->boolean('blocked')->default(false);
            $table->boolean('direct_posting')->default(true);
            $table->boolean('reconciliation_account')->default(false);
            $table->integer('no_of_blank_lines')->default(0);
            $table->integer('indentation')->default(0);
            $table->string('totaling', 100)->nullable();
            $table->string('global_dimension_1_filter', 20)->nullable();
            $table->string('global_dimension_2_filter', 20)->nullable();
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();
            $table->foreignId('dimension_set_id')->nullable();
            $table->timestamp('last_modified_date_time')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_no');
            $table->index('account_type');
            $table->index('account_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gl_accounts');
    }
};
