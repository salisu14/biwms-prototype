<?php

declare(strict_types=1);

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
        Schema::create('workforce_roster_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workforce_roster_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date')->index();
            $table->foreignId('employee_shift_id')->constrained('employee_shifts')->restrictOnDelete();
            $table->foreignId('attendance_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->foreignId('roster_role_id')->nullable()->constrained('workforce_roster_roles')->nullOnDelete();
            $table->string('assignment_type')->default('regular')->index();
            $table->string('status')->default('draft')->index();
            $table->string('source_reference_type')->nullable();
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->foreignId('original_assignment_id')->nullable()->constrained('workforce_roster_assignments')->nullOnDelete();
            $table->foreignId('replaced_by_assignment_id')->nullable()->constrained('workforce_roster_assignments')->nullOnDelete();
            $table->timestamp('expected_start_at')->nullable();
            $table->timestamp('expected_end_at')->nullable();
            $table->unsignedInteger('break_minutes')->nullable();
            $table->boolean('may_create_overtime')->default(false);
            $table->string('conflict_status')->nullable()->index();
            $table->json('conflict_details')->nullable();
            $table->unsignedInteger('forecast_overtime_minutes')->default(0);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date', 'status'], 'workforce_roster_assignment_employee_date_index');
            $table->index(['expected_start_at', 'expected_end_at'], 'workforce_roster_assignment_expected_index');
            $table->index(['attendance_location_id', 'work_date', 'status'], 'workforce_roster_assignment_location_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workforce_roster_assignments');
    }
};
