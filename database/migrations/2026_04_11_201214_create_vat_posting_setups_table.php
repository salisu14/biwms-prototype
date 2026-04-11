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
        Schema::create('vat_posting_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vat_business_posting_group_id')->constrained('vat_business_posting_groups')->onDelete('cascade');
            $table->foreignId('vat_product_posting_group_id')->constrained('vat_product_posting_groups')->onDelete('cascade');
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->foreignId('sales_vat_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('purchase_vat_account_id')->nullable()->constrained('chart_of_accounts');
            $table->timestamps();

            $table->unique(['vat_business_posting_group_id', 'vat_product_posting_group_id'], 'vat_posting_setup_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_posting_setups');
    }
};
