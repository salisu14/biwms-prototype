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
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('days');
            $table->boolean('paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->decimal('attachment_required_after_days', 8, 2)->nullable();
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('allow_negative_balance')->default(false);
            $table->boolean('requires_manager_approval')->default(true);
            $table->boolean('requires_hr_approval')->default(true);
            $table->string('color', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'code']);
        });

        Schema::create('leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('assignment_type')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });

        Schema::create('leave_policy_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('leave_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('annual_entitlement', 10, 2)->default(0);
            $table->string('accrual_frequency', 20)->default('none');
            $table->decimal('accrual_amount', 10, 2)->nullable();
            $table->decimal('maximum_balance', 10, 2)->nullable();
            $table->decimal('maximum_consecutive_days', 10, 2)->nullable();
            $table->boolean('carry_forward_allowed')->default(false);
            $table->decimal('maximum_carry_forward', 10, 2)->nullable();
            $table->unsignedInteger('carry_forward_expiry_months')->nullable();
            $table->unsignedInteger('minimum_service_months')->default(0);
            $table->unsignedInteger('notice_days')->default(0);
            $table->boolean('allow_negative_balance')->default(false);
            $table->boolean('requires_manager_approval')->default(true);
            $table->boolean('requires_hr_approval')->default(true);
            $table->timestamps();

            $table->unique(['leave_policy_id', 'leave_type_id']);
        });

        Schema::create('employee_leave_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('leave_year');
            $table->decimal('opening_balance', 10, 2)->default(0);
            $table->decimal('entitled_amount', 10, 2)->default(0);
            $table->decimal('carried_forward', 10, 2)->default(0);
            $table->date('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'leave_year']);
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_number')->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('start_part', 20)->default('full_day');
            $table->string('end_part', 20)->default('full_day');
            $table->decimal('requested_quantity', 10, 2)->default(0);
            $table->decimal('approved_quantity', 10, 2)->nullable();
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('contact_during_leave')->nullable();
            $table->text('handover_notes')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('payroll_review_required')->default(false);
            $table->string('payroll_impact_status')->nullable();
            $table->string('payroll_reference')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('manager_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_approved_at')->nullable();
            $table->foreignId('hr_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status', 'start_date', 'end_date']);
            $table->index(['leave_type_id', 'status']);
        });

        Schema::create('employee_leave_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();
            $table->unsignedInteger('leave_year');
            $table->string('entry_type', 30);
            $table->decimal('quantity', 10, 2);
            $table->date('posting_date');
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['leave_request_id', 'entry_type']);
            $table->index(['employee_id', 'leave_type_id', 'leave_year']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('leave_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('holiday_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'holiday_date', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_holidays');
        Schema::dropIfExists('employee_leave_ledger_entries');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('employee_leave_entitlements');
        Schema::dropIfExists('leave_policy_rules');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
