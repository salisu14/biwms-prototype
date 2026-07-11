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
        Schema::create('attendance_payroll_review_batch_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_payroll_review_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_attendance_day_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_review_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_payroll_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('line_type')->index();
            $table->unsignedInteger('quantity_minutes')->nullable();
            $table->decimal('quantity_days', 8, 4)->nullable();
            $table->decimal('suggested_amount', 15, 4)->nullable();
            $table->decimal('approved_amount', 15, 4)->nullable();
            $table->string('currency', 10)->nullable();
            $table->json('calculation_basis')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('payroll_adjustment_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['attendance_payroll_review_batch_id', 'employee_id', 'attendance_review_item_id', 'line_type'], 'attendance_payroll_batch_line_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_payroll_review_batch_lines');
    }
};
