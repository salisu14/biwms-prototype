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
        // CapEx Projects (Work-in-Progress Assets)
        Schema::create('capex_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_number')->unique();
            $table->string('description');

            // Project status workflow
            $table->string('status')->default('DRAFT'); // DRAFT, PENDING_APPROVAL, APPROVED, IN_PROGRESS, COMPLETED, CANCELLED, ON_HOLD

            // Target asset (populated when project completes)
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets');

            // Budget tracking
            $table->decimal('budget_amount', 15, 2)->default(0);
            $table->decimal('committed_amount', 15, 2)->default(0); // POs placed but not yet received
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->decimal('capitalized_amount', 15, 2)->default(0);

            // Timeline
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            // Capitalization rules
            $table->boolean('capitalize_labor')->default(true);
            $table->boolean('capitalize_materials')->default(true);
            $table->boolean('capitalize_overhead')->default(false);
            $table->boolean('capitalize_interest')->default(false);
            $table->decimal('capitalization_threshold', 15, 2)->default(5000); // Minimum amount to capitalize

            // GL accounts
            $table->foreignId('wip_gl_account_id')->constrained('chart_of_accounts'); // Construction in Progress
            $table->foreignId('capex_gl_account_id')->constrained('chart_of_accounts'); // Fixed Asset account

            // Interest capitalization (for long-term projects)
            $table->decimal('interest_capitalization_rate', 5, 2)->nullable(); // Weighted average borrowing rate
            $table->decimal('capitalized_interest_to_date', 15, 2)->default(0);

            // Project management
            $table->foreignId('project_manager_id')->constrained('users');
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('last_modified_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'planned_end_date']);
            $table->index(['project_manager_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capex_projects');
    }
};
