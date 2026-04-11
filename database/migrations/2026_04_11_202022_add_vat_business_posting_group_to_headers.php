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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('vat_business_posting_group_id')->nullable()->constrained('vat_business_posting_groups');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('vat_business_posting_group_id')->nullable()->constrained('vat_business_posting_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['vat_business_posting_group_id']);
            $table->dropColumn('vat_business_posting_group_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['vat_business_posting_group_id']);
            $table->dropColumn('vat_business_posting_group_id');
        });
    }
};
