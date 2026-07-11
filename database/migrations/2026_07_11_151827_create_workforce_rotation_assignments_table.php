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
        Schema::create('workforce_rotation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workforce_rotation_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->date('cycle_start_date');
            $table->unsignedInteger('starting_sequence_day')->default(1);
            $table->foreignId('attendance_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->boolean('is_primary')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from', 'effective_to', 'is_primary'], 'workforce_rotation_assignment_scope_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workforce_rotation_assignments');
    }
};
