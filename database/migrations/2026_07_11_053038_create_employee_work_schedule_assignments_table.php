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
        Schema::create('employee_work_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_shift_id')->constrained('employee_shifts')->restrictOnDelete();
            $table->date('effective_from')->index();
            $table->date('effective_until')->nullable()->index();
            $table->json('working_days')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from', 'effective_until'], 'employee_schedule_effective_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_work_schedule_assignments');
    }
};
