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
        Schema::create('general_posting_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_business_posting_group_id')
                ->constrained('general_business_posting_groups');
            $table->foreignId('general_product_posting_group_id')
                ->constrained('general_product_posting_groups');


            // Sales accounts
            $table->foreignId('sales_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('sales_credit_memo_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('sales_prepayment_account_id')->nullable()->constrained('chart_of_accounts');

            // COGS accounts
            $table->foreignId('cogs_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('cogs_credit_memo_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('cogs_prepayment_account_id')->nullable()->constrained('chart_of_accounts');

            // Purchase/Inventory accounts
            $table->foreignId('inventory_adj_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('inventory_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('direct_cost_applied_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('overhead_applied_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('purchase_variance_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('material_variance_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('capacity_variance_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('capacity_overhead_variance_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('manufacturing_overhead_variance_account_id')->nullable()->constrained('chart_of_accounts');


            $table->boolean('blocked')->default(false);
            $table->timestamps();

            // Unique combination
            $table->unique([
                'general_business_posting_group_id',
                'general_product_posting_group_id'
            ], 'unique_posting_setup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_posting_setups');
    }
};
