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
        Schema::create('workforce_shift_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_roster_assignment_id')->constrained('workforce_roster_assignments')->restrictOnDelete();
            $table->foreignId('original_employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('replacement_employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('replacement_roster_assignment_id')->nullable()->constrained('workforce_roster_assignments')->nullOnDelete();
            $table->string('replacement_type')->default('absence_replacement')->index();
            $table->text('reason');
            $table->string('status')->default('draft')->index();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('may_create_overtime')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workforce_shift_replacements');
    }
};
