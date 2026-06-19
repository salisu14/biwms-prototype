<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Expense/Revenue Categories Master
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();

            // Classification
            $table->string('account_type', 30); // AccountType enum
            $table->string('category_code', 30); // ExpenseCategoryEnum, RevenueCategory, or COGSCategory
            $table->string('category_type', 30); // Which enum it belongs to

            // Description
            $table->string('description', 100);
            $table->text('notes')->nullable();

            // Behavior
            $table->boolean('is_direct')->default(false);
            $table->boolean('is_variable')->default(true);
            $table->boolean('is_controllable')->default(true);

            //            // Product category link (for sales returns, discounts)
            $table->foreignId('product_category_id')->nullable()->constrained('categories');

            // Default GL accounts
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('contra_account_id')->nullable()->constrained('chart_of_accounts'); // For returns/discounts

            // Posting setup
            $table->json('posting_rules')->nullable(); // Business rules for automatic posting

            // Dimensions default
            $table->string('default_dimension_1', 20)->nullable(); // Default department
            $table->string('default_dimension_2', 20)->nullable(); // Default project


            $table->foreignId('gen_prod_posting_group_id')->nullable()->constrained('general_product_posting_groups');
            $table->foreignId('vat_prod_posting_group_id')->nullable()->constrained('vat_product_posting_groups');


            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['account_type', 'category_code']);
            $table->index('is_active');
        });

        // Expense/Revenue Transactions
        Schema::create('expense_transactions', function (Blueprint $table) {
            $table->id();

            // Document reference
            $table->string('document_type', 30); // invoice, credit_memo, journal, adjustment
            $table->string('document_no', 20);
            $table->date('posting_date');
            $table->date('document_date')->nullable();

            // Classification
            $table->string('account_type', 30); // AccountType
            $table->string('category_code', 30); // Specific category
            $table->string('expense_type', 30)->nullable(); // direct, indirect

            // Amounts
            $table->decimal('amount', 18, 4);
            $table->decimal('amount_lcy', 18, 4);
            $table->string('currency_code', 10)->nullable();
            $table->decimal('currency_factor', 18, 6)->default(1);

            // VAT/Tax
            $table->decimal('vat_amount', 18, 4)->default(0);
            $table->string('vat_bus_posting_group', 20)->nullable();
            //            $table->string('vat_prod_posting_group', 20)->nullable();

            // Source tracking
            $table->foreignId('vendor_id')->nullable()->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('employee_id')->nullable()->constrained('employees');

            // Product/Item link (for COGS, sales returns)
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->foreignId('product_category_id')->nullable()->constrained('categories');

            $table->foreignId('currency_id')->nullable()->constrained('currencies');

            // Reference documents
            $table->string('purchase_order_no', 20)->nullable();
            $table->string('sales_order_no', 20)->nullable();
            $table->string('invoice_no', 20)->nullable();

            // Dimensions
            $table->string('shortcut_dimension_1_code', 20)->nullable(); // Department
            $table->string('shortcut_dimension_2_code', 20)->nullable(); // Project
            $table->json('dimensions')->nullable();

            // GL Posting
            $table->foreignId('gl_entry_id')->nullable()->constrained('gl_entries');
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts');

            // Status
            $table->string('status', 20)->default('posted'); // posted, reversed, pending
            $table->foreignId('reversed_by')->nullable()->constrained('expense_transactions');

            // User tracking
            $table->foreignId('posted_by')->constrained('users');
            $table->text('description')->nullable();

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

            $table->timestamps();
            $table->softDeletes();

            $table->index(['posting_date', 'account_type']);
            $table->index(['category_code', 'posting_date']);
            $table->index(['product_category_id', 'posting_date']);
            $table->index('vendor_id');
            $table->index('customer_id');
            $table->index('document_no');
        });

        // Expense allocation (for indirect expenses allocation to cost centers)
        Schema::create('expense_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_transaction_id')->constrained('expense_transactions');

            $table->string('allocation_basis', 30); // labor_hours, machine_hours, square_footage, revenue
            $table->decimal('allocation_percentage', 5, 2);
            $table->decimal('allocated_amount', 18, 4);

            $table->string('target_dimension_1', 20)->nullable(); // Target department
            $table->string('target_dimension_2', 20)->nullable(); // Target project
            $table->foreignId('target_gl_account_id')->constrained('chart_of_accounts');

            $table->foreignId('gl_entry_id')->nullable()->constrained('gl_entries');

            $table->string('allocation_type')
                ->default('percentage')
                ->comment('percentage, amount');

            $table->timestamps();
        });

        // Budget vs Actual tracking
        Schema::create('expense_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('budget_name', 50);
            $table->integer('fiscal_year');

            $table->string('account_type', 30);
            $table->string('category_code', 30);
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();

            $table->decimal('january', 18, 4)->default(0);
            $table->decimal('february', 18, 4)->default(0);
            $table->decimal('march', 18, 4)->default(0);
            $table->decimal('april', 18, 4)->default(0);
            $table->decimal('may', 18, 4)->default(0);
            $table->decimal('june', 18, 4)->default(0);
            $table->decimal('july', 18, 4)->default(0);
            $table->decimal('august', 18, 4)->default(0);
            $table->decimal('september', 18, 4)->default(0);
            $table->decimal('october', 18, 4)->default(0);
            $table->decimal('november', 18, 4)->default(0);
            $table->decimal('december', 18, 4)->default(0);
            $table->decimal('annual_total', 18, 4)->default(0);

            $table->foreignId('currency_id')->nullable()->constrained('currencies');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['fiscal_year', 'account_type', 'category_code', 'shortcut_dimension_1_code', 'shortcut_dimension_2_code'], 'budget_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_budgets');
        Schema::dropIfExists('expense_allocations');
        Schema::dropIfExists('expense_transactions');
        Schema::dropIfExists('expense_categories');
    }
};
