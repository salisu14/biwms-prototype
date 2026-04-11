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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('vat_business_posting_group_id')->nullable()->constrained('vat_business_posting_groups');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->foreignId('vat_business_posting_group_id')->nullable()->constrained('vat_business_posting_groups');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('vat_product_posting_group_id')->nullable()->constrained('vat_product_posting_groups');
            // We will keep vat_id for now to avoid breaking existing code until refactor is complete
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['vat_business_posting_group_id']);
            $table->dropColumn('vat_business_posting_group_id');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropForeign(['vat_business_posting_group_id']);
            $table->dropColumn('vat_business_posting_group_id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['vat_product_posting_group_id']);
            $table->dropColumn('vat_product_posting_group_id');
        });
    }
};
