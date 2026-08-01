<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CommissionLiabilityPostingStatus;
use App\Enums\CommissionPaymentApplicationType;
use App\Enums\CommissionPaymentBatchStatus;
use App\Enums\CommissionPaymentLineStatus;
use App\Enums\CommissionPaymentMethod;
use App\Enums\CommissionReviewBatchStatus;
use App\Enums\CommissionReviewPeriodStatus;
use App\Enums\CommissionSettlementBatchStatus;
use App\Enums\CommissionSettlementLineStatus;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionLiabilityPosting;
use App\Models\CommissionPaymentApplication;
use App\Models\CommissionPaymentBatch;
use App\Models\CommissionReviewBatch;
use App\Models\CommissionReviewPeriod;
use App\Models\CommissionSettlementAllocation;
use App\Models\CommissionSettlementBatch;
use App\Models\CommissionSettlementLine;
use App\Models\GlEntry;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Permission;
use App\Models\ReferralCommissionSetting;
use App\Models\Referrer;
use App\Models\User;
use App\Services\Sales\ReferralCommissions\CommissionLiabilityPostingService;
use App\Services\Sales\ReferralCommissions\CommissionPaymentReversalService;
use App\Services\Sales\ReferralCommissions\CommissionPaymentService;
use App\Services\Sales\ReferralCommissions\ReferrerCommissionPaymentBalanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

test('locked settlement liability posting creates balanced gl and commission ledger evidence', function (): void {
    [$actor, $settlement] = commissionPhase6LockedSettlement('125.0000');

    $posting = app(CommissionLiabilityPostingService::class)->post($settlement, $actor, '2026-08-01');
    $again = app(CommissionLiabilityPostingService::class)->post($settlement, $actor, '2026-08-01');

    expect($again->id)->toBe($posting->id)
        ->and($posting->status)->toBe(CommissionLiabilityPostingStatus::Posted)
        ->and((float) $posting->net_liability_amount)->toBe(125.0)
        ->and((float) GlEntry::query()->where('posting_transaction_id', $posting->posting_transaction_id)->sum('debit_amount'))->toBe(125.0)
        ->and((float) GlEntry::query()->where('posting_transaction_id', $posting->posting_transaction_id)->sum('credit_amount'))->toBe(125.0)
        ->and(CommissionLedgerEntry::query()->where('entry_type', CommissionLedgerEntryType::LiabilityRecognition)->where('source_id', $posting->id)->count())->toBe(1);
});

test('commission payment batch posts bank ledger gl applications and balances', function (): void {
    [$actor, $settlement, $bankAccount] = commissionPhase6LockedSettlement('200.0000');
    app(CommissionLiabilityPostingService::class)->post($settlement, $actor, '2026-08-01');

    $paymentService = app(CommissionPaymentService::class);
    $batch = $paymentService->createBatchFromSettlement($settlement, [
        'payment_method' => CommissionPaymentMethod::BankTransfer->value,
        'bank_account_id' => $bankAccount->id,
        'payment_date' => '2026-08-02',
        'posting_date' => '2026-08-02',
    ], $actor);

    $batch = $paymentService->prepare($batch, $actor);
    $batch = $paymentService->submit($batch, $actor);
    $approver = commissionPhase6User();
    $batch = $paymentService->approve($batch, $approver);
    $posted = $paymentService->post($batch, $approver);
    $again = $paymentService->post($posted, $approver);

    expect($again->id)->toBe($posted->id)
        ->and($posted->status)->toBe(CommissionPaymentBatchStatus::Posted)
        ->and($posted->lines()->first()->status)->toBe(CommissionPaymentLineStatus::Posted)
        ->and(BankAccountLedgerEntry::query()->where('source_type', 'commission_payment')->where('source_id', $posted->id)->count())->toBe(1)
        ->and(CommissionPaymentApplication::query()->where('commission_payment_batch_id', $posted->id)->where('application_type', CommissionPaymentApplicationType::Payment)->sum('applied_amount'))->toBe('200.0000')
        ->and((float) GlEntry::query()->where('posting_transaction_id', $posted->posting_transaction_id)->sum('debit_amount'))->toBe(200.0)
        ->and((float) GlEntry::query()->where('posting_transaction_id', $posted->posting_transaction_id)->sum('credit_amount'))->toBe(200.0)
        ->and(app(ReferrerCommissionPaymentBalanceService::class)->balances(['referrer_id' => $posted->lines()->first()->referrer_id])[0]['outstanding_amount'])->toBe(0.0);
});

test('partial commission payment preserves outstanding and blocks overpayment', function (): void {
    [$actor, $settlement, $bankAccount] = commissionPhase6LockedSettlement('300.0000');
    app(CommissionLiabilityPostingService::class)->post($settlement, $actor, '2026-08-01');
    $line = $settlement->lines()->firstOrFail();

    $batch = app(CommissionPaymentService::class)->createBatchFromSettlement($settlement, [
        'payment_method' => CommissionPaymentMethod::BankTransfer->value,
        'bank_account_id' => $bankAccount->id,
        'payment_date' => '2026-08-02',
        'posting_date' => '2026-08-02',
        'line_amounts' => [$line->id => '120.0000'],
    ], $actor);

    expect((float) $batch->total_amount)->toBe(120.0)
        ->and((float) $batch->lines()->first()->remaining_amount)->toBe(180.0);

    expect(fn () => app(CommissionPaymentService::class)->createBatchFromSettlement($settlement, [
        'payment_method' => CommissionPaymentMethod::BankTransfer->value,
        'bank_account_id' => $bankAccount->id,
        'payment_date' => '2026-08-03',
        'posting_date' => '2026-08-03',
        'line_amounts' => [$line->id => '301.0000'],
    ], $actor))->toThrow(RuntimeException::class, 'exceeds');
});

test('commission payment reversal restores outstanding through append only applications', function (): void {
    [$actor, $settlement, $bankAccount] = commissionPhase6LockedSettlement('90.0000');
    app(CommissionLiabilityPostingService::class)->post($settlement, $actor, '2026-08-01');
    $paymentService = app(CommissionPaymentService::class);
    $batch = $paymentService->createBatchFromSettlement($settlement, [
        'payment_method' => CommissionPaymentMethod::BankTransfer->value,
        'bank_account_id' => $bankAccount->id,
        'payment_date' => '2026-08-02',
        'posting_date' => '2026-08-02',
    ], $actor);
    $batch = $paymentService->approve($paymentService->submit($paymentService->prepare($batch, $actor), $actor), commissionPhase6User());
    $posted = $paymentService->post($batch, commissionPhase6User());

    $reversed = app(CommissionPaymentReversalService::class)->reverseBatch($posted, commissionPhase6User(), 'Returned bank file');

    expect($reversed->status)->toBe(CommissionPaymentBatchStatus::Reversed)
        ->and(CommissionPaymentApplication::query()->where('commission_payment_batch_id', $posted->id)->where('application_type', CommissionPaymentApplicationType::Reversal)->sum('applied_amount'))->toBe('90.0000')
        ->and(app(ReferrerCommissionPaymentBalanceService::class)->balances(['referrer_id' => $posted->lines()->first()->referrer_id])[0]['outstanding_amount'])->toBe(90.0);
});

test('commission payment authorization and self approval protections are enforced', function (): void {
    [$actor, $settlement, $bankAccount] = commissionPhase6LockedSettlement('75.0000');
    app(CommissionLiabilityPostingService::class)->post($settlement, $actor, '2026-08-01');
    $batch = app(CommissionPaymentService::class)->createBatchFromSettlement($settlement, [
        'payment_method' => CommissionPaymentMethod::BankTransfer->value,
        'bank_account_id' => $bankAccount->id,
        'payment_date' => '2026-08-02',
        'posting_date' => '2026-08-02',
    ], $actor);
    $batch = app(CommissionPaymentService::class)->submit(app(CommissionPaymentService::class)->prepare($batch, $actor), $actor);

    expect(fn () => app(CommissionPaymentService::class)->approve($batch, $actor))->toThrow(RuntimeException::class, 'cannot approve')
        ->and(fn () => app(CommissionPaymentService::class)->post($batch, User::factory()->create()))->toThrow(AuthorizationException::class);
});

test('commission reconcile reports phase six findings and remains report only', function (): void {
    [$actor, $settlement] = commissionPhase6LockedSettlement('50.0000');
    $before = [
        CommissionLiabilityPosting::query()->count(),
        CommissionPaymentBatch::query()->count(),
        CommissionPaymentApplication::query()->count(),
    ];
    $exportPath = storage_path('app/reports/commission-phase6-reconcile-test.json');
    File::delete($exportPath);

    Artisan::call('biwms:commission-reconcile', ['--details' => true, '--export' => $exportPath]);
    $report = json_decode(File::get($exportPath), true);

    expect($report['findings']['locked_settlement_without_liability'])->not->toBeEmpty()
        ->and($report['findings']['locked_settlement_without_liability'][0]['classification'])->toBe('locked_settlement_without_liability')
        ->and([
            CommissionLiabilityPosting::query()->count(),
            CommissionPaymentBatch::query()->count(),
            CommissionPaymentApplication::query()->count(),
        ])->toBe($before);

    app(CommissionLiabilityPostingService::class)->post($settlement, $actor, '2026-08-01');
});

function commissionPhase6LockedSettlement(string $amount): array
{
    commissionPhase6NumberSeries();
    AccountingPeriod::query()->firstOrCreate(
        ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
        ['name' => 'FY2026', 'is_closed' => false],
    );
    $actor = commissionPhase6User();
    $business = Business::query()->create([
        'code' => 'BIZ-P6-'.str()->random(6),
        'name' => 'Commission Phase 6 Business',
        'is_active' => true,
    ]);
    $referrer = Referrer::factory()->create([
        'business_id' => $business->id,
        'commission_eligible' => true,
        'is_active' => true,
    ]);

    $expense = ChartOfAccount::factory()->create(['account_category' => AccountCategory::OPERATING_EXPENSE]);
    $payable = ChartOfAccount::factory()->create(['account_category' => AccountCategory::LIABILITY]);
    ReferralCommissionSetting::query()->create([
        'business_id' => $business->id,
        'is_enabled' => true,
        'default_commission_basis' => 'POSTED_SALES',
        'require_plan_assignment' => false,
        'include_tax_in_commission_base' => false,
        'include_shipping_in_commission_base' => false,
        'deduct_line_discounts' => true,
        'deduct_invoice_discounts' => true,
        'allow_commission_on_zero_value_lines' => false,
        'allow_commission_on_free_items' => false,
        'allow_commission_for_inactive_referrer' => false,
        'commission_expense_account_id' => $expense->id,
        'commission_payable_account_id' => $payable->id,
        'minimum_eligible_sale_amount' => 0,
        'commission_decimal_places' => 4,
    ]);

    $bankAccount = BankAccount::factory()->create([
        'current_balance' => 1000,
        'available_balance' => 1000,
    ]);

    $sourceLedger = CommissionLedgerEntry::query()->create([
        'business_id' => $business->id,
        'entry_type' => CommissionLedgerEntryType::Accrual,
        'referrer_id' => $referrer->id,
        'source_type' => Referrer::class,
        'source_id' => $referrer->id,
        'source_number' => 'INV-P6-'.str()->random(5),
        'posting_date' => '2026-07-15',
        'currency_code' => 'NGN',
        'amount' => $amount,
        'base_amount' => $amount,
        'status' => CommissionLedgerEntryStatus::ApprovedForFuturePayment,
        'idempotency_key' => hash('sha256', 'phase6-ledger|'.$referrer->id.'|'.$amount.'|'.str()->random(8)),
    ]);
    $reviewPeriod = CommissionReviewPeriod::query()->create([
        'business_id' => $business->id,
        'code' => 'COMM-P6-'.str()->random(6),
        'name' => 'Phase 6 Period',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'status' => CommissionReviewPeriodStatus::Locked,
        'locked_at' => now(),
        'locked_by' => $actor->id,
    ]);
    $reviewBatch = CommissionReviewBatch::query()->create([
        'business_id' => $business->id,
        'commission_review_period_id' => $reviewPeriod->id,
        'batch_number' => 'CRB-P6-'.str()->random(6),
        'currency_code' => 'NGN',
        'status' => CommissionReviewBatchStatus::Locked,
        'referrer_scope' => 'all',
        'cutoff_date' => '2026-07-31',
        'generated_at' => now(),
        'generated_by' => $actor->id,
        'locked_at' => now(),
        'locked_by' => $actor->id,
        'total_accrual_amount' => $amount,
        'total_eligible_amount' => $amount,
        'line_count' => 1,
        'idempotency_key' => hash('sha256', 'phase6-review-batch|'.$sourceLedger->id),
    ]);
    $settlement = CommissionSettlementBatch::query()->create([
        'business_id' => $business->id,
        'settlement_number' => 'CSB-P6-'.str()->random(6),
        'commission_review_period_id' => $reviewPeriod->id,
        'commission_review_batch_id' => $reviewBatch->id,
        'currency_code' => 'NGN',
        'status' => CommissionSettlementBatchStatus::Locked,
        'settlement_date' => '2026-08-01',
        'cutoff_date' => '2026-07-31',
        'locked_at' => now(),
        'locked_by' => $actor->id,
        'total_gross_amount' => $amount,
        'total_hold_amount' => 0,
        'total_forfeiture_amount' => 0,
        'total_adjustment_amount' => 0,
        'total_net_amount' => $amount,
        'referrer_count' => 1,
        'line_count' => 1,
        'idempotency_key' => hash('sha256', 'phase6-settlement|'.$referrer->id.'|'.$amount.'|'.str()->random(8)),
        'snapshot_version' => 1,
    ]);
    $line = CommissionSettlementLine::query()->create([
        'business_id' => $business->id,
        'commission_settlement_batch_id' => $settlement->id,
        'commission_review_batch_id' => $reviewBatch->id,
        'referrer_id' => $referrer->id,
        'currency_code' => 'NGN',
        'gross_amount' => $amount,
        'hold_amount' => 0,
        'forfeiture_amount' => 0,
        'adjustment_amount' => 0,
        'net_settlement_amount' => $amount,
        'status' => CommissionSettlementLineStatus::Locked,
        'idempotency_key' => hash('sha256', 'phase6-settlement-line|'.$settlement->id.'|'.$referrer->id),
    ]);
    CommissionSettlementAllocation::query()->create([
        'business_id' => $business->id,
        'commission_settlement_batch_id' => $settlement->id,
        'commission_settlement_line_id' => $line->id,
        'commission_ledger_entry_id' => $sourceLedger->id,
        'allocated_amount' => $amount,
        'currency_code' => 'NGN',
        'allocation_type' => 'accrual',
        'idempotency_key' => hash('sha256', 'phase6-allocation|'.$line->id.'|'.$sourceLedger->id),
    ]);

    return [$actor, $settlement->fresh(['lines.allocations']), $bankAccount];
}

function commissionPhase6User(): User
{
    $user = User::factory()->create();
    foreach (commissionPhase6Permissions() as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $user->givePermissionTo(commissionPhase6Permissions());

    return $user;
}

function commissionPhase6Permissions(): array
{
    return [
        'sales.commission_liability.post',
        'sales.commission_liability.reverse',
        'sales.commission_payment_batch.create',
        'sales.commission_payment_batch.prepare',
        'sales.commission_payment_batch.submit',
        'sales.commission_payment_batch.approve',
        'sales.commission_payment_batch.post',
        'sales.commission_payment_batch.cancel',
        'sales.commission_payment_batch.reverse',
    ];
}

function commissionPhase6NumberSeries(): void
{
    $series = NumberSeries::query()->firstOrCreate(
        ['code' => 'BANK-LEDGER'],
        [
            'description' => 'Bank Ledger Entries',
            'prefix' => '',
            'starting_number' => 1,
            'ending_number' => null,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'allow_manual' => false,
            'module' => 'finance',
        ],
    );

    NumberSeriesLine::query()->firstOrCreate(
        ['number_series_id' => $series->id, 'starting_date' => now()->startOfYear()->toDateString()],
        [
            'prefix' => '',
            'suffix' => '',
            'starting_no' => 0,
            'ending_no' => null,
            'increment_by' => 1,
            'last_no_used' => 0,
            'no_of_digits' => 6,
            'blocked' => false,
        ],
    );
}
