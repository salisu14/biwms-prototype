<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_commission_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('referral_commission_settings', 'commission_expense_account_id')) {
                $table->foreignId('commission_expense_account_id')->nullable()->after('commission_currency_id')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('referral_commission_settings', 'commission_payable_account_id')) {
                $table->foreignId('commission_payable_account_id')->nullable()->after('commission_expense_account_id')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('referral_commission_settings', 'commission_rounding_account_id')) {
                $table->foreignId('commission_rounding_account_id')->nullable()->after('commission_payable_account_id')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('referral_commission_settings', 'commission_payment_clearing_account_id')) {
                $table->foreignId('commission_payment_clearing_account_id')->nullable()->after('commission_rounding_account_id')->constrained('chart_of_accounts')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('commission_liability_postings')) {
            Schema::create('commission_liability_postings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->restrictOnDelete();
                $table->foreignId('commission_settlement_batch_id')->constrained('commission_settlement_batches')->restrictOnDelete();
                $table->string('currency_code', 10);
                $table->date('posting_date');
                $table->string('document_number', 50);
                $table->string('status', 30)->default('pending');
                $table->decimal('gross_amount', 18, 4)->default(0);
                $table->decimal('withholding_amount', 18, 4)->default(0);
                $table->decimal('net_liability_amount', 18, 4)->default(0);
                $table->foreignId('posting_transaction_id')->nullable()->constrained('posting_transactions')->restrictOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reversal_posting_transaction_id')->nullable()->constrained('posting_transactions')->restrictOnDelete();
                $table->string('idempotency_key', 128)->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique('commission_settlement_batch_id', 'commission_liability_batch_unique');
                $table->unique(['business_id', 'document_number'], 'commission_liability_business_document_unique');
                $table->index(['business_id', 'currency_code', 'status']);
            });
        }

        if (! Schema::hasTable('commission_payment_batches')) {
            Schema::create('commission_payment_batches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->restrictOnDelete();
                $table->string('batch_number', 50);
                $table->foreignId('commission_settlement_batch_id')->constrained('commission_settlement_batches')->restrictOnDelete();
                $table->string('currency_code', 10);
                $table->date('payment_date');
                $table->date('posting_date');
                $table->string('payment_method', 40);
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
                $table->foreignId('cash_account_id')->nullable()->constrained('petty_cash_funds')->restrictOnDelete();
                $table->string('status', 40)->default('draft');
                $table->text('description')->nullable();
                $table->string('external_reference')->nullable();
                $table->timestamp('prepared_at')->nullable();
                $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('cancellation_reason')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('total_amount', 18, 4)->default(0);
                $table->unsignedInteger('line_count')->default(0);
                $table->unsignedInteger('referrer_count')->default(0);
                $table->foreignId('posting_transaction_id')->nullable()->constrained('posting_transactions')->restrictOnDelete();
                $table->string('idempotency_key', 128)->unique();
                $table->string('failure_code')->nullable();
                $table->text('failure_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'batch_number'], 'commission_payment_batch_business_number_unique');
                $table->index(['commission_settlement_batch_id', 'status']);
                $table->index(['business_id', 'currency_code', 'payment_method', 'payment_date']);
            });
        }

        if (! Schema::hasTable('commission_payment_lines')) {
            Schema::create('commission_payment_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->restrictOnDelete();
                $table->foreignId('commission_payment_batch_id')->constrained('commission_payment_batches')->restrictOnDelete();
                $table->foreignId('commission_settlement_batch_id')->constrained('commission_settlement_batches')->restrictOnDelete();
                $table->foreignId('commission_settlement_line_id')->constrained('commission_settlement_lines')->restrictOnDelete();
                $table->foreignId('referrer_id')->constrained('referrers')->restrictOnDelete();
                $table->string('currency_code', 10);
                $table->decimal('approved_amount', 18, 4)->default(0);
                $table->decimal('previously_paid_amount', 18, 4)->default(0);
                $table->decimal('payment_amount', 18, 4)->default(0);
                $table->decimal('remaining_amount', 18, 4)->default(0);
                $table->string('payment_method', 40);
                $table->string('beneficiary_name')->nullable();
                $table->string('masked_payment_reference')->nullable();
                $table->string('external_reference')->nullable();
                $table->string('status', 40)->default('draft');
                $table->string('exception_code')->nullable();
                $table->text('exception_message')->nullable();
                $table->foreignId('posting_transaction_id')->nullable()->constrained('posting_transactions')->restrictOnDelete();
                $table->string('idempotency_key', 128)->unique();
                $table->json('snapshot')->nullable();
                $table->timestamps();

                $table->unique(['commission_payment_batch_id', 'commission_settlement_line_id'], 'commission_payment_line_batch_settlement_unique');
                $table->index(['referrer_id', 'currency_code']);
                $table->index(['commission_settlement_batch_id', 'status']);
            });
        }

        if (! Schema::hasTable('commission_payment_applications')) {
            Schema::create('commission_payment_applications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->restrictOnDelete();
                $table->foreignId('commission_payment_batch_id')->constrained('commission_payment_batches')->restrictOnDelete();
                $table->foreignId('commission_payment_line_id')->constrained('commission_payment_lines')->restrictOnDelete();
                $table->foreignId('commission_settlement_allocation_id')->constrained('commission_settlement_allocations')->restrictOnDelete();
                $table->foreignId('commission_ledger_entry_id')->nullable()->constrained('commission_ledger_entries')->restrictOnDelete();
                $table->foreignId('referrer_id')->constrained('referrers')->restrictOnDelete();
                $table->string('currency_code', 10);
                $table->decimal('applied_amount', 18, 4);
                $table->string('application_type', 40)->default('payment');
                $table->string('status', 30)->default('applied');
                $table->foreignId('reverses_application_id')->nullable()->constrained('commission_payment_applications')->restrictOnDelete();
                $table->foreignId('reversed_by_application_id')->nullable()->constrained('commission_payment_applications')->restrictOnDelete();
                $table->date('posting_date');
                $table->string('idempotency_key', 128)->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['commission_settlement_allocation_id', 'status']);
                $table->index(['referrer_id', 'currency_code', 'application_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payment_applications');
        Schema::dropIfExists('commission_payment_lines');
        Schema::dropIfExists('commission_payment_batches');
        Schema::dropIfExists('commission_liability_postings');

        Schema::table('referral_commission_settings', function (Blueprint $table): void {
            foreach ([
                'commission_payment_clearing_account_id',
                'commission_rounding_account_id',
                'commission_payable_account_id',
                'commission_expense_account_id',
            ] as $column) {
                if (Schema::hasColumn('referral_commission_settings', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
