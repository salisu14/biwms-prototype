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
        Schema::create('employee_promotion_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('effective_date');
            $table->string('reason_code', 255)->default('PROMOTION');
            $table->string('old_job_title')->nullable();
            $table->string('new_job_title');
            $table->foreignId('old_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('new_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->decimal('old_base_salary', 15, 4)->default(0);
            $table->decimal('new_base_salary', 15, 4);
            $table->text('audit_note');
            $table->foreignId('promoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
            $table->index(['effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_promotion_histories');
    }
};
