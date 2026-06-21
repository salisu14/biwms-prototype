<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('account_number', 50)->unique();
            $table->string('name', 100);
            $table->string('search_name', 100)->nullable();

            // Structural Type (Posting, Heading, Totals)
            $table->string('structural_type')->default('posting');

            // Account Nature (Asset, Liability, etc.)
            $table->string('account_category');

            // Financial Statement destination (0=BS, 1=IS)
            $table->string('income_balance')->default(0);

            // Layout & Formatting
            $table->string('totaling', 100)->nullable();
            $table->tinyInteger('indentation')->default(0);
            $table->boolean('bold')->default(false);
            $table->boolean('italic')->default(false);
            $table->boolean('underline')->default(false);
            $table->boolean('show_opposite_sign')->default(false);
            $table->boolean('new_page')->default(false);
            $table->tinyInteger('no_of_blank_lines')->default(0);

            // Posting Controls
            $table->boolean('direct_posting')->default(true);
            $table->boolean('blocked')->default(false);
            $table->date('blocked_from')->nullable();
            $table->date('blocked_to')->nullable();

            // Dimensions
            $table->string('shortcut_dimension_1_code', 50)->nullable();
            $table->string('shortcut_dimension_2_code', 50)->nullable();

            // UPDATED: Posting Groups as Foreign Keys
            $table->foreignId('gen_bus_posting_group_id')->nullable()->constrained('general_business_posting_groups')->nullOnDelete();
            $table->foreignId('gen_prod_posting_group_id')->nullable()->constrained('general_product_posting_groups')->nullOnDelete();

            // Assuming VAT groups also have their own master tables (Standard BC practice)
            $table->foreignId('vat_bus_posting_group_id')->nullable()->constrained('vat_business_posting_groups')->nullOnDelete();
            $table->foreignId('vat_prod_posting_group_id')->nullable()->constrained('vat_product_posting_groups')->nullOnDelete();

            // Cost Accounting & Consolidation
            $table->string('cost_type_no', 20)->nullable();
            $table->string('consol_debit_acc', 50)->nullable();
            $table->string('consol_credit_acc', 50)->nullable();
            $table->string('consol_translation_method')->nullable();

            // Hierarchy
            $table->foreignId('parent_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Balances
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('balance_at_date', 15, 2)->nullable();

            $table->string('gl_account_type')->default('Posting');

            $table->string('account_type')->default('Asset');

            $table->timestamps();

            // Optimized Indexes
            $table->index(['structural_type', 'account_number']);
            $table->index(['account_category', 'income_balance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
