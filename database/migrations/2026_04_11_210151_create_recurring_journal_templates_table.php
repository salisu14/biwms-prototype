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
        Schema::create('recurring_journal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 100)->nullable();
            $table->foreignId('number_series_id')->constrained('number_series');
            $table->foreignId('posting_number_series_id')->nullable()->constrained('number_series');
            $table->string('source_code', 20)->nullable();

            // Recurring controls
            $table->enum('recurring_method', ['fixed', 'variable', 'balance', 'reversing_fixed', 'reversing_variable', 'reversing_balance'])->default('fixed');
            $table->string('recurring_frequency', 20)->default('monthly'); // daily, weekly, monthly, quarterly, yearly
            $table->integer('recurring_interval')->default(1); // Every N periods
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('auto_post')->default(false);
            $table->timestamp('last_posting_date')->nullable();
            $table->timestamp('next_posting_date')->nullable();

            // Calculation formulas for variable/balance methods
            $table->text('calculation_formula')->nullable(); // e.g., "GL(1000).Balance * 0.05"
            $table->decimal('fixed_amount', 15, 4)->nullable();

            // Reversal settings
            $table->boolean('auto_reverse')->default(false); // For reversing methods
            $table->integer('reversal_days_offset')->default(1); // Reverse N days after posting

            // Account and dimension controls (inherited from General Journal)
            $table->foreignId('default_balancing_account_id')->nullable()->constrained('chart_of_accounts');
            $table->json('mandatory_dimensions')->nullable();
            $table->json('default_dimensions')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recurring_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('recurring_journal_templates')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('description', 100)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'processing', 'posted', 'cancelled'])->default('open');
            $table->date('current_processing_date')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'name']);
        });

        Schema::create('recurring_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('recurring_journal_batches')->cascadeOnDelete();
            $table->integer('line_no')->default(10000);

            // Recurring-specific fields
            $table->enum('recurring_method', ['fixed', 'variable', 'balance', 'reversing_fixed', 'reversing_variable', 'reversing_balance']);
            $table->date('starting_date');
            $table->date('ending_date')->nullable();
            $table->date('expiration_date')->nullable(); // Line stops recurring after this date

            // Account information (same structure as General Journal)
            $table->date('posting_date')->nullable(); // Calculated during processing
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->string('account_type', 20)->default('gl');
            $table->foreignId('balancing_account_id')->nullable()->constrained('chart_of_accounts');
            $table->text('description');

            // Amount calculation
            $table->decimal('amount', 15, 4)->nullable(); // For fixed
            $table->string('calculation_formula', 255)->nullable(); // For variable/balance
            $table->string('account_to_calculate_balance', 50)->nullable(); // GL account for balance method
            $table->decimal('percentage_for_balance', 5, 2)->nullable(); // % of balance

            // Dimensions
            $table->string('shortcut_dimension_1_code', 50)->nullable();
            $table->string('shortcut_dimension_2_code', 50)->nullable();
            $table->json('dimension_set_entry')->nullable();

            // Allocation (distribute to multiple dimensions)
            $table->boolean('use_allocation')->default(false);
            $table->foreignId('allocation_id')->nullable()->constrained('allocations');

            // Business information
            $table->string('source_code', 20)->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at');

            // Processing tracking
            $table->date('last_posting_date')->nullable();
            $table->date('next_posting_date')->nullable();
            $table->integer('posting_count')->default(0);
            $table->enum('line_status', ['active', 'expired', 'on_hold'])->default('active');

            $table->unique(['batch_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_journal_lines');
        Schema::dropIfExists('recurring_journal_batches');
        Schema::dropIfExists('recurring_journal_templates');
    }
};
