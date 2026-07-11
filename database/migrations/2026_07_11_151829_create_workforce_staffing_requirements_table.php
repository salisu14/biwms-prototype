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
        Schema::create('workforce_staffing_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->foreignId('attendance_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_shift_id')->nullable()->constrained('employee_shifts')->nullOnDelete();
            $table->foreignId('roster_role_id')->constrained('workforce_roster_roles')->restrictOnDelete();
            $table->unsignedTinyInteger('weekday')->nullable()->index();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->unsignedInteger('minimum_required')->default(0);
            $table->unsignedInteger('target_required')->nullable();
            $table->unsignedInteger('maximum_allowed')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['department_id', 'work_center_id', 'attendance_location_id', 'employee_shift_id'], 'workforce_staffing_requirement_scope_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workforce_staffing_requirements');
    }
};
