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
        Schema::create('employee_attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_shift_id')->nullable()->constrained('employee_shifts')->nullOnDelete();
            $table->foreignId('attendance_ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date')->index();
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('first_clock_in_at')->nullable();
            $table->timestamp('last_clock_out_at')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_departure_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status')->default('absent')->index();
            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_weekend')->default(false);
            $table->boolean('on_leave')->default(false);
            $table->boolean('missing_clock_out')->default(false)->index();
            $table->boolean('payroll_review_required')->default(false)->index();
            $table->string('payroll_impact_status')->default('pending_review');
            $table->json('calculation_notes')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_days');
    }
};
