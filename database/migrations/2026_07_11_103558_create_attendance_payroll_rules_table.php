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
        Schema::create('attendance_payroll_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('impact_type')->default('informational')->index();
            $table->string('attendance_issue_type')->index();
            $table->string('calculation_method')->default('manual');
            $table->decimal('rate', 15, 4)->nullable();
            $table->unsignedInteger('minimum_minutes')->nullable();
            $table->unsignedInteger('maximum_minutes')->nullable();
            $table->string('rounding_rule')->nullable();
            $table->foreignId('earning_component_id')->nullable()->constrained('pay_codes')->nullOnDelete();
            $table->foreignId('deduction_component_id')->nullable()->constrained('pay_codes')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_payroll_rules');
    }
};
