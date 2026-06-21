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
        Schema::create('employee_pay_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_code_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->nullable(); // Override default
            $table->decimal('percentage', 5, 2)->nullable(); // Override default
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
            
            $table->unique(['employee_id', 'pay_code_id', 'effective_date'], 'emp_pay_code_effective_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_pay_codes');
    }
};
