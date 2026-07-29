<?php

declare(strict_types=1);

use App\Enums\CommissionCalculationBasis;
use App\Enums\CommissionCalculationStatus;
use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('referral_commission_plans') && ! Schema::hasColumn('referral_commission_plans', 'calculation_basis')) {
            Schema::table('referral_commission_plans', function (Blueprint $table): void {
                $table->string('calculation_basis', 40)
                    ->default(CommissionCalculationBasis::LineNetAmount->value)
                    ->after('commission_basis');
            });
        }

        if (! Schema::hasTable('commission_calculations')) {
            Schema::create('commission_calculations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->string('source_type');
                $table->unsignedBigInteger('source_id');
                $table->string('source_number', 80);
                $table->date('source_posting_date');
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('customer_referral_id')->nullable()->constrained('customer_referrals')->nullOnDelete();
                $table->foreignId('referrer_id')->nullable()->constrained('referrers')->nullOnDelete();
                $table->foreignId('commission_plan_id')->nullable()->constrained('referral_commission_plans')->nullOnDelete();
                $table->string('currency_code', 10)->default('NGN');
                $table->string('calculation_status', 40)->default(CommissionCalculationStatus::Pending->value);
                $table->decimal('calculated_base_amount', 18, 4)->default(0);
                $table->decimal('calculated_commission_amount', 18, 4)->default(0);
                $table->unsignedInteger('eligible_line_count')->default(0);
                $table->unsignedInteger('ineligible_line_count')->default(0);
                $table->timestamp('calculated_at')->nullable();
                $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('calculation_version')->default(1);
                $table->string('idempotency_key', 160)->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'source_type', 'source_id', 'calculation_version'], 'commission_calculations_source_version_unique');
                $table->index(['business_id', 'calculation_status']);
                $table->index(['referrer_id', 'source_posting_date']);
                $table->index(['commission_plan_id', 'source_posting_date']);
            });
        }

        if (! Schema::hasTable('commission_calculation_lines')) {
            Schema::create('commission_calculation_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('commission_calculation_id')->constrained('commission_calculations')->restrictOnDelete();
                $table->string('source_line_type');
                $table->unsignedBigInteger('source_line_id');
                $table->unsignedInteger('source_line_number')->nullable();
                $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
                $table->string('description')->nullable();
                $table->decimal('quantity', 18, 8)->default(0);
                $table->foreignId('unit_of_measure_id')->nullable()->constrained('unit_of_measures')->nullOnDelete();
                $table->decimal('gross_amount', 18, 4)->default(0);
                $table->decimal('discount_amount', 18, 4)->default(0);
                $table->decimal('net_amount', 18, 4)->default(0);
                $table->decimal('recognized_cost_amount', 18, 4)->default(0);
                $table->decimal('gross_profit_amount', 18, 4)->default(0);
                $table->decimal('eligible_base_amount', 18, 4)->default(0);
                $table->string('commission_basis', 40)->default(CommissionCalculationBasis::LineNetAmount->value);
                $table->decimal('commission_rate', 9, 4)->nullable();
                $table->decimal('fixed_commission_amount', 18, 4)->nullable();
                $table->decimal('calculated_commission_amount', 18, 4)->default(0);
                $table->foreignId('commission_plan_rule_id')->nullable();
                $table->foreignId('commission_tier_id')->nullable()->constrained('referral_commission_plan_tiers')->nullOnDelete();
                $table->string('eligibility_status', 30)->default('ineligible');
                $table->string('ineligibility_reason')->nullable();
                $table->json('calculation_snapshot')->nullable();
                $table->string('idempotency_key', 160)->unique();
                $table->timestamps();

                $table->unique(['commission_calculation_id', 'source_line_type', 'source_line_id'], 'commission_calc_lines_source_unique');
                $table->index(['item_id', 'eligibility_status']);
            });
        }

        if (! Schema::hasTable('commission_ledger_entries')) {
            Schema::create('commission_ledger_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->unsignedBigInteger('entry_number')->unique();
                $table->string('entry_type', 80)->default(CommissionLedgerEntryType::Accrual->value);
                $table->foreignId('referrer_id')->nullable()->constrained('referrers')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('customer_referral_id')->nullable()->constrained('customer_referrals')->nullOnDelete();
                $table->foreignId('commission_calculation_id')->nullable()->constrained('commission_calculations')->nullOnDelete();
                $table->foreignId('commission_calculation_line_id')->nullable()->constrained('commission_calculation_lines')->nullOnDelete();
                $table->string('source_type');
                $table->unsignedBigInteger('source_id');
                $table->unsignedBigInteger('source_line_id')->nullable();
                $table->string('source_number', 80);
                $table->date('posting_date');
                $table->string('currency_code', 10)->default('NGN');
                $table->decimal('amount', 18, 4)->default(0);
                $table->decimal('base_amount', 18, 4)->default(0);
                $table->string('status', 80)->default(CommissionLedgerEntryStatus::Open->value);
                $table->foreignId('reverses_entry_id')->nullable()->constrained('commission_ledger_entries')->nullOnDelete();
                $table->foreignId('reversed_by_entry_id')->nullable()->constrained('commission_ledger_entries')->nullOnDelete();
                $table->string('idempotency_key', 160)->unique();
                $table->string('reason_code', 50)->nullable();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['business_id', 'status']);
                $table->index(['referrer_id', 'posting_date']);
                $table->index(['commission_calculation_id', 'entry_type']);
                $table->index(['source_type', 'source_id', 'source_line_id'], 'commission_ledger_source_index');
                $table->index('reverses_entry_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_ledger_entries');
        Schema::dropIfExists('commission_calculation_lines');
        Schema::dropIfExists('commission_calculations');

        if (Schema::hasTable('referral_commission_plans') && Schema::hasColumn('referral_commission_plans', 'calculation_basis')) {
            Schema::table('referral_commission_plans', function (Blueprint $table): void {
                $table->dropColumn('calculation_basis');
            });
        }
    }
};
