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
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->foreignId('gen_prod_posting_group_id')->nullable()->constrained('general_product_posting_groups');
            $table->foreignId('vat_prod_posting_group_id')->nullable()->constrained('vat_product_posting_groups');
        });

        Schema::table('expense_transactions', function (Blueprint $table) {
            // Posting Groups
            $table->foreignId('gen_bus_posting_group_id')->nullable()->constrained('general_business_posting_groups');
            $table->foreignId('gen_prod_posting_group_id')->nullable()->constrained('general_product_posting_groups');
            $table->foreignId('vat_bus_posting_group_id')->nullable()->constrained('vat_business_posting_groups');
            $table->foreignId('vat_prod_posting_group_id')->nullable()->constrained('vat_product_posting_groups');

            // Dimensions
            $table->foreignId('dimension_set_id')->nullable()->constrained('dimension_sets');

            // Traceability
            $table->string('source_type', 30)->nullable()->comment('VENDOR, CUSTOMER, EMPLOYEE, BANK, FA');
            $table->string('source_no', 50)->nullable();
        });

        Schema::table('expense_allocations', function (Blueprint $table) {
            $table->foreignId('dimension_set_id')->nullable()->constrained('dimension_sets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dimension_set_id');
        });

        Schema::table('expense_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gen_bus_posting_group_id');
            $table->dropConstrainedForeignId('gen_prod_posting_group_id');
            $table->dropConstrainedForeignId('vat_bus_posting_group_id');
            $table->dropConstrainedForeignId('vat_prod_posting_group_id');
            $table->dropConstrainedForeignId('dimension_set_id');
            $table->dropColumn(['source_type', 'source_no']);
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gen_prod_posting_group_id');
            $table->dropConstrainedForeignId('vat_prod_posting_group_id');
        });
    }
};
