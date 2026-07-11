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
        Schema::create('attendance_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_review_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_attendance_day_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date')->index();
            $table->string('issue_type')->index();
            $table->string('severity')->default('warning')->index();
            $table->string('review_status')->default('pending')->index();
            $table->json('original_values')->nullable();
            $table->json('resolved_values')->nullable();
            $table->string('source_hash')->nullable();
            $table->string('resolution_type')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['attendance_review_period_id', 'employee_attendance_day_id', 'issue_type'], 'attendance_review_item_unique');
            $table->index(['employee_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_review_items');
    }
};
