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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            // BC-style codes
            $table->string('department_code', 20)->unique(); // Dimension value code
            $table->string('name', 100);
            $table->string('search_name', 100)->nullable();

            // Hierarchy (for organizational structure)
            $table->foreignId('parent_department_id')->nullable()->constrained('departments');
            $table->integer('level')->default(0); // 0 = root, 1 = sub-dept, etc.
            $table->string('department_path', 255)->nullable(); // Full path: "100|110|115"

            // Classification
            $table->string('type', 30)->default('operating'); // DepartmentType enum
            $table->string('status', 20)->default('active'); // DepartmentStatus enum

            // Dimension integration (links to your existing dimensions)
            $table->foreignId('dimension_value_id')->nullable()->constrained('dimension_values');
            $table->string('global_dimension_1_code', 20)->nullable(); // Shortcut to dimension

            // Cost center / Profit center attributes
            $table->boolean('is_cost_center')->default(true);
            $table->boolean('is_profit_center')->default(false);
            $table->string('cost_center_code', 20)->nullable();
            $table->string('profit_center_code', 20)->nullable();

            // Manager/Head of department
            $table->foreignId('manager_id')->nullable()->constrained('employees');
            $table->foreignId('approver_id')->nullable()->constrained('users'); // For approval workflows

            // Location (if department is location-specific)
            $table->string('location_code', 10)->nullable();
            //            $table->foreignId('company_id')->nullable()->constrained('companies'); // Multi-company support

            // Budgeting
            $table->decimal('annual_budget', 18, 4)->nullable();
            $table->decimal('budget_utilized', 18, 4)->default(0);

            // Accounting defaults
            $table->string('default_expense_account', 20)->nullable();
            $table->string('default_project_code', 20)->nullable();

            // Contact info
            $table->string('email', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('room_location', 50)->nullable();

            // Metadata
            $table->date('starting_date')->nullable();
            $table->date('ending_date')->nullable();
            $table->text('notes')->nullable();

            // BC-style timestamps
            $table->timestamp('blocked_at')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'type']);
            $table->index('global_dimension_1_code');
            $table->index(['parent_department_id', 'level']);
            $table->index('manager_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
