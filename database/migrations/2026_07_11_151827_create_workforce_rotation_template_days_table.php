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
        Schema::create('workforce_rotation_template_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workforce_rotation_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence_day');
            $table->foreignId('employee_shift_id')->nullable()->constrained('employee_shifts')->nullOnDelete();
            $table->boolean('is_rest_day')->default(false);
            $table->foreignId('attendance_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->foreignId('roster_role_id')->nullable()->constrained('workforce_roster_roles')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['workforce_rotation_template_id', 'sequence_day'], 'workforce_rotation_template_day_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workforce_rotation_template_days');
    }
};
