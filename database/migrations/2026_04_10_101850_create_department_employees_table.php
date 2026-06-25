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
        Schema::create('department_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('employee_id')->constrained('employees');

            // Assignment details
            $table->string('assignment_type', 20)->default('primary'); // primary, secondary, temporary
            $table->string('position_title', 100)->nullable();
            $table->date('assignment_date');
            $table->date('end_date')->nullable();

            // BC-style fields
            $table->decimal('allocation_percentage', 5, 2)->default(100); // % of time in dept
            $table->boolean('is_default_dimension')->default(false); // Auto-populate on transactions

            $table->timestamps();

            $table->unique(['department_id', 'employee_id', 'assignment_type']);
            $table->index(['employee_id', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_employee');
    }
};
