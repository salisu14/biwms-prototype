<?php

declare(strict_types=1);

use App\Enums\CommissionDisputeStatus;
use App\Enums\CommissionHoldStatus;
use App\Enums\CommissionHoldType;
use App\Enums\CommissionReviewBatchStatus;
use App\Enums\CommissionReviewLineStatus;
use App\Enums\CommissionReviewPeriodStatus;
use App\Enums\CommissionSettlementBatchStatus;
use App\Enums\CommissionSettlementLineStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commission_review_periods')) {
            Schema::create('commission_review_periods', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->string('code', 60);
                $table->string('name');
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 40)->default(CommissionReviewPeriodStatus::Draft->value);
                $table->string('currency_mode', 40)->default('separate');
                $table->text('description')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reopened_at')->nullable();
                $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reopen_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'code'], 'commission_review_periods_business_code_unique');
                $table->index(['business_id', 'period_start', 'period_end'], 'commission_review_periods_business_dates_index');
                $table->index(['business_id', 'status']);
            });
        }

        if (! Schema::hasTable('commission_review_batches')) {
            Schema::create('commission_review_batches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->foreignId('commission_review_period_id')->constrained('commission_review_periods')->restrictOnDelete();
                $table->string('batch_number', 80)->unique();
                $table->string('currency_code', 10);
                $table->string('status', 40)->default(CommissionReviewBatchStatus::Draft->value);
                $table->string('referrer_scope', 80)->default('all');
                $table->date('calculation_date')->nullable();
                $table->date('cutoff_date');
                $table->timestamp('generated_at')->nullable();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('total_accrual_amount', 18, 4)->default(0);
                $table->decimal('total_adjustment_amount', 18, 4)->default(0);
                $table->decimal('total_reversal_amount', 18, 4)->default(0);
                $table->decimal('total_hold_amount', 18, 4)->default(0);
                $table->decimal('total_forfeiture_amount', 18, 4)->default(0);
                $table->decimal('total_eligible_amount', 18, 4)->default(0);
                $table->unsignedInteger('line_count')->default(0);
                $table->unsignedInteger('exception_count')->default(0);
                $table->string('idempotency_key', 160)->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['commission_review_period_id', 'currency_code', 'referrer_scope', 'cutoff_date'], 'commission_review_batches_generation_unique');
                $table->index(['business_id', 'status']);
                $table->index(['commission_review_period_id', 'status'], 'commission_review_batches_period_status_index');
            });
        }

        if (! Schema::hasTable('commission_review_batch_lines')) {
            Schema::create('commission_review_batch_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->foreignId('commission_review_batch_id')->constrained('commission_review_batches')->restrictOnDelete();
                $table->foreignId('referrer_id')->nullable()->constrained('referrers')->nullOnDelete();
                $table->string('currency_code', 10);
                $table->foreignId('commission_ledger_entry_id')->constrained('commission_ledger_entries')->restrictOnDelete();
                $table->foreignId('commission_calculation_id')->nullable()->constrained('commission_calculations')->nullOnDelete();
                $table->foreignId('commission_calculation_line_id')->nullable()->constrained('commission_calculation_lines')->nullOnDelete();
                $table->string('source_type');
                $table->unsignedBigInteger('source_id');
                $table->string('source_number', 80);
                $table->date('source_posting_date');
                $table->string('entry_type', 80);
                $table->decimal('original_amount', 18, 4)->default(0);
                $table->decimal('eligible_amount', 18, 4)->default(0);
                $table->decimal('held_amount', 18, 4)->default(0);
                $table->decimal('forfeited_amount', 18, 4)->default(0);
                $table->decimal('approved_amount', 18, 4)->default(0);
                $table->string('review_status', 40)->default(CommissionReviewLineStatus::Pending->value);
                $table->string('exception_status', 40)->nullable();
                $table->string('exception_code', 80)->nullable();
                $table->text('exception_message')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('snapshot')->nullable();
                $table->string('idempotency_key', 160)->unique();
                $table->timestamps();

                $table->unique('commission_ledger_entry_id', 'commission_review_lines_ledger_unique');
                $table->index(['commission_review_batch_id', 'review_status'], 'commission_review_lines_batch_status_index');
                $table->index(['referrer_id', 'currency_code'], 'commission_review_lines_referrer_currency_index');
            });
        }

        if (! Schema::hasTable('commission_holds')) {
            Schema::create('commission_holds', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->foreignId('referrer_id')->nullable()->constrained('referrers')->nullOnDelete();
                $table->foreignId('commission_review_batch_id')->nullable()->constrained('commission_review_batches')->restrictOnDelete();
                $table->foreignId('commission_review_batch_line_id')->nullable()->constrained('commission_review_batch_lines')->restrictOnDelete();
                $table->foreignId('commission_ledger_entry_id')->nullable()->constrained('commission_ledger_entries')->restrictOnDelete();
                $table->string('hold_type', 50)->default(CommissionHoldType::Manual->value);
                $table->string('status', 40)->default(CommissionHoldStatus::Active->value);
                $table->decimal('amount', 18, 4)->default(0);
                $table->string('currency_code', 10);
                $table->string('reason_code', 80)->nullable();
                $table->text('reason');
                $table->timestamp('placed_at');
                $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('released_at')->nullable();
                $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('release_reason')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('idempotency_key', 160)->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'status']);
                $table->index(['commission_review_batch_line_id', 'status'], 'commission_holds_line_status_index');
            });
        }

        if (! Schema::hasTable('commission_disputes')) {
            Schema::create('commission_disputes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->string('dispute_number', 80)->unique();
                $table->foreignId('referrer_id')->nullable()->constrained('referrers')->nullOnDelete();
                $table->foreignId('commission_review_period_id')->nullable()->constrained('commission_review_periods')->restrictOnDelete();
                $table->foreignId('commission_review_batch_id')->nullable()->constrained('commission_review_batches')->restrictOnDelete();
                $table->foreignId('commission_review_batch_line_id')->nullable()->constrained('commission_review_batch_lines')->restrictOnDelete();
                $table->foreignId('commission_ledger_entry_id')->nullable()->constrained('commission_ledger_entries')->restrictOnDelete();
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('status', 40)->default(CommissionDisputeStatus::Open->value);
                $table->string('dispute_type', 80);
                $table->decimal('claimed_amount', 18, 4)->default(0);
                $table->string('currency_code', 10);
                $table->string('subject');
                $table->text('description')->nullable();
                $table->timestamp('raised_at');
                $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution')->nullable();
                $table->string('resolution_code', 80)->nullable();
                $table->foreignId('approved_adjustment_id')->nullable()->constrained('commission_ledger_entries')->nullOnDelete();
                $table->string('idempotency_key', 160)->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'status']);
                $table->index(['referrer_id', 'status']);
            });
        }

        if (! Schema::hasTable('commission_settlement_batches')) {
            Schema::create('commission_settlement_batches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->string('settlement_number', 80)->unique();
                $table->foreignId('commission_review_period_id')->constrained('commission_review_periods')->restrictOnDelete();
                $table->foreignId('commission_review_batch_id')->constrained('commission_review_batches')->restrictOnDelete();
                $table->string('currency_code', 10);
                $table->string('status', 40)->default(CommissionSettlementBatchStatus::Draft->value);
                $table->date('settlement_date');
                $table->date('cutoff_date');
                $table->text('description')->nullable();
                $table->timestamp('prepared_at')->nullable();
                $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('rejection_reason')->nullable();
                $table->decimal('total_gross_amount', 18, 4)->default(0);
                $table->decimal('total_hold_amount', 18, 4)->default(0);
                $table->decimal('total_forfeiture_amount', 18, 4)->default(0);
                $table->decimal('total_adjustment_amount', 18, 4)->default(0);
                $table->decimal('total_net_amount', 18, 4)->default(0);
                $table->unsignedInteger('referrer_count')->default(0);
                $table->unsignedInteger('line_count')->default(0);
                $table->string('idempotency_key', 160)->unique();
                $table->unsignedInteger('snapshot_version')->default(1);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['commission_review_batch_id', 'currency_code', 'cutoff_date'], 'commission_settlement_batches_generation_unique');
                $table->index(['business_id', 'status']);
            });
        }

        if (! Schema::hasTable('commission_settlement_lines')) {
            Schema::create('commission_settlement_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->foreignId('commission_settlement_batch_id')->constrained('commission_settlement_batches')->restrictOnDelete();
                $table->foreignId('commission_review_batch_id')->constrained('commission_review_batches')->restrictOnDelete();
                $table->foreignId('commission_review_batch_line_id')->nullable()->constrained('commission_review_batch_lines')->restrictOnDelete();
                $table->foreignId('referrer_id')->nullable()->constrained('referrers')->nullOnDelete();
                $table->string('currency_code', 10);
                $table->decimal('gross_amount', 18, 4)->default(0);
                $table->decimal('hold_amount', 18, 4)->default(0);
                $table->decimal('forfeiture_amount', 18, 4)->default(0);
                $table->decimal('adjustment_amount', 18, 4)->default(0);
                $table->decimal('net_settlement_amount', 18, 4)->default(0);
                $table->string('status', 40)->default(CommissionSettlementLineStatus::Prepared->value);
                $table->string('exception_code', 80)->nullable();
                $table->text('exception_message')->nullable();
                $table->json('snapshot')->nullable();
                $table->string('idempotency_key', 160)->unique();
                $table->timestamps();

                $table->unique(['commission_settlement_batch_id', 'referrer_id', 'currency_code'], 'commission_settlement_lines_referrer_unique');
                $table->index(['referrer_id', 'currency_code']);
            });
        }

        if (! Schema::hasTable('commission_settlement_allocations')) {
            Schema::create('commission_settlement_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->foreignId('commission_settlement_batch_id')->constrained('commission_settlement_batches')->restrictOnDelete();
                $table->foreignId('commission_settlement_line_id')->constrained('commission_settlement_lines')->restrictOnDelete();
                $table->foreignId('commission_ledger_entry_id')->constrained('commission_ledger_entries')->restrictOnDelete();
                $table->decimal('allocated_amount', 18, 4)->default(0);
                $table->string('currency_code', 10);
                $table->string('allocation_type', 80);
                $table->string('idempotency_key', 160)->unique();
                $table->timestamps();

                $table->unique('commission_ledger_entry_id', 'commission_settlement_allocations_ledger_unique');
                $table->index(['commission_settlement_batch_id', 'currency_code'], 'commission_settlement_allocations_batch_currency_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settlement_allocations');
        Schema::dropIfExists('commission_settlement_lines');
        Schema::dropIfExists('commission_settlement_batches');
        Schema::dropIfExists('commission_disputes');
        Schema::dropIfExists('commission_holds');
        Schema::dropIfExists('commission_review_batch_lines');
        Schema::dropIfExists('commission_review_batches');
        Schema::dropIfExists('commission_review_periods');
    }
};
