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
        Schema::create('actual_overhead_costs', function (Blueprint $table) {
            $table->id();

            // Cost center reference (Work Center, Machine Center, or Location)
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->foreignId('machine_center_id')->nullable()->constrained('machine_centers')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('location_masters')->nullOnDelete();

            // Period reference
            $table->date('period'); // First day of the month
            $table->year('fiscal_year');
            $table->tinyInteger('period_no'); // 1-12 for monthly, 1-4 for quarterly

            // Overhead category
            $table->string('cost_type', 50); // rent, utilities, depreciation, insurance, indirect_labor, maintenance, etc.
            $table->string('cost_type_code', 20)->nullable(); // For grouping/reporting

            // Amounts
            $table->decimal('amount', 15, 4);
            $table->decimal('allocated_amount', 15, 4)->default(0); // Amount allocated to production
            $table->decimal('remaining_amount', 15, 4)->virtualAs('amount - allocated_amount');

            // GL Account reference
            $table->foreignId('gl_account_id')->constrained('chart_of_accounts');
            $table->string('gl_account_no', 50); // Denormalized

            // Document reference
            $table->string('document_type', 50)->nullable(); // invoice, journal, accrual
            $table->string('document_no', 50)->nullable();
            $table->date('document_date')->nullable();

            // Description and notes
            $table->text('description');
            $table->text('notes')->nullable();

            // Allocation status
            $table->enum('status', ['unallocated', 'partial', 'fully_allocated', 'variance_posted'])->default('unallocated');

            // Variance posting reference
            $table->foreignId('variance_journal_batch_id')->nullable()->constrained('general_journal_batches')->nullOnDelete();
            $table->timestamp('variance_posted_at')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['work_center_id', 'period', 'cost_type']);
            $table->index(['location_id', 'period']);
            $table->index(['fiscal_year', 'period_no']);
            $table->index(['status', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actual_overhead_costs');
    }
};
