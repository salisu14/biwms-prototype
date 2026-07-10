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
        Schema::create('employee_payslips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payroll_line_id')->nullable()->unique()->constrained('payroll_lines')->nullOnDelete();
            $table->string('payslip_number')->unique();
            $table->string('verification_token')->unique()->nullable();
            $table->string('status')->default('generated');
            $table->string('currency_code', 10)->default('NGN');
            $table->date('payment_date')->nullable();
            $table->decimal('worked_days', 10, 2)->default(0);
            $table->decimal('gross_earnings', 15, 4)->default(0);
            $table->decimal('total_deductions', 15, 4)->default(0);
            $table->decimal('net_pay', 15, 4)->default(0);
            $table->string('amount_in_words')->nullable();
            $table->string('employee_number')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('employee_email')->nullable();
            $table->string('employee_phone')->nullable();
            $table->string('department_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_email')->nullable();
            $table->string('company_logo_path')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'payroll_period_id', 'payroll_document_id']);
            $table->index(['status', 'payment_date']);
        });

        Schema::create('employee_payslip_earnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payslip_id')->constrained('employee_payslips')->cascadeOnDelete();
            $table->foreignId('pay_code_id')->nullable()->constrained('pay_codes')->nullOnDelete();
            $table->string('pay_code')->nullable();
            $table->string('description');
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('rate', 15, 4)->default(0);
            $table->decimal('amount', 15, 4)->default(0);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_payslip_deductions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payslip_id')->constrained('employee_payslips')->cascadeOnDelete();
            $table->foreignId('pay_code_id')->nullable()->constrained('pay_codes')->nullOnDelete();
            $table->string('pay_code')->nullable();
            $table->string('description');
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('rate', 15, 4)->default(0);
            $table->decimal('amount', 15, 4)->default(0);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_payslip_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payslip_id')->nullable()->constrained('employee_payslips')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['event', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_payslip_histories');
        Schema::dropIfExists('employee_payslip_deductions');
        Schema::dropIfExists('employee_payslip_earnings');
        Schema::dropIfExists('employee_payslips');
    }
};
