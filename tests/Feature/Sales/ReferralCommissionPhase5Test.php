<?php

declare(strict_types=1);

use App\Enums\CommissionDisputeStatus;
use App\Enums\CommissionDisputeType;
use App\Enums\CommissionHoldStatus;
use App\Enums\CommissionHoldType;
use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CommissionReviewBatchStatus;
use App\Enums\CommissionReviewLineStatus;
use App\Enums\CommissionReviewPeriodStatus;
use App\Enums\CommissionSettlementBatchStatus;
use App\Models\Business;
use App\Models\CommissionDispute;
use App\Models\CommissionHold;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionReviewBatch;
use App\Models\CommissionReviewBatchLine;
use App\Models\CommissionReviewPeriod;
use App\Models\CommissionSettlementAllocation;
use App\Models\CommissionSettlementBatch;
use App\Models\GlEntry;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Referrer;
use App\Models\User;
use App\Services\Sales\ReferralCommissions\CommissionForfeitureService;
use App\Services\Sales\ReferralCommissions\CommissionReviewBatchService;
use App\Services\Sales\ReferralCommissions\CommissionReviewPeriodService;
use App\Services\Sales\ReferralCommissions\CommissionReviewService;
use App\Services\Sales\ReferralCommissions\CommissionSettlementPreparationService;
use App\Services\Sales\ReferralCommissions\ReferrerCommissionApprovalBalanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('commission review periods enforce date rules and workflow transitions', function (): void {
    [$actor, $business] = commissionPhase5Actor();

    $service = app(CommissionReviewPeriodService::class);
    $period = $service->create([
        'business_id' => $business->id,
        'code' => 'COMM-2026-07',
        'name' => 'July 2026',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
    ], $actor);

    expect($period->status)->toBe(CommissionReviewPeriodStatus::Draft);

    expect(fn () => $service->create([
        'business_id' => $business->id,
        'code' => 'COMM-OVERLAP',
        'name' => 'Overlap',
        'period_start' => '2026-07-31',
        'period_end' => '2026-08-15',
    ], $actor))->toThrow(RuntimeException::class, 'overlaps');

    $adjacent = $service->create([
        'business_id' => $business->id,
        'code' => 'COMM-2026-08',
        'name' => 'August 2026',
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ], $actor);

    $period = $service->open($period, $actor);
    $period = $service->submit($period, $actor);
    $period = $service->approve($period, $actor);
    $period = $service->lock($period, $actor);

    expect($adjacent->status)->toBe(CommissionReviewPeriodStatus::Draft)
        ->and($period->status)->toBe(CommissionReviewPeriodStatus::Locked);

    expect(fn () => $service->reopen($period, $actor, ''))->toThrow(RuntimeException::class, 'reason');

    $reopened = $service->reopen($period, $actor, 'Correct review membership');
    expect($reopened->status)->toBe(CommissionReviewPeriodStatus::Reopened)
        ->and($reopened->reopen_reason)->toBe('Correct review membership');
});

test('review batch generation includes eligible ledger entries once and separates currencies', function (): void {
    [$actor, $business, $referrer] = commissionPhase5Actor();
    $period = commissionPhase5OpenPeriod($actor, $business);
    $ngnEntry = commissionPhase5LedgerEntry($business, $referrer, '100.0000', 'NGN', 'INV-NGN');
    $usdEntry = commissionPhase5LedgerEntry($business, $referrer, '25.0000', 'USD', 'INV-USD');
    commissionPhase5LedgerEntry($business, $referrer, '50.0000', 'NGN', 'INV-LATE', '2026-08-01');

    $batchService = app(CommissionReviewBatchService::class);
    $ngnBatch = $batchService->generate($period, 'NGN', '2026-07-31', $actor);
    $again = $batchService->generate($period, 'NGN', '2026-07-31', $actor);
    $usdBatch = $batchService->generate($period, 'USD', '2026-07-31', $actor);

    expect($again->id)->toBe($ngnBatch->id)
        ->and($ngnBatch->currency_code)->toBe('NGN')
        ->and($ngnBatch->lines)->toHaveCount(1)
        ->and($ngnBatch->lines->first()->commission_ledger_entry_id)->toBe($ngnEntry->id)
        ->and((float) $ngnBatch->fresh()->total_eligible_amount)->toBe(100.0)
        ->and($usdBatch->lines)->toHaveCount(1)
        ->and($usdBatch->lines->first()->commission_ledger_entry_id)->toBe($usdEntry->id);
});

test('review holds disputes forfeiture and approval are service controlled and auditable', function (): void {
    [$actor, $business, $referrer] = commissionPhase5Actor();
    $period = commissionPhase5OpenPeriod($actor, $business);
    commissionPhase5LedgerEntry($business, $referrer, '100.0000');
    $batch = app(CommissionReviewBatchService::class)->generate($period, 'NGN', '2026-07-31', $actor);
    $line = $batch->lines()->firstOrFail();

    $review = app(CommissionReviewService::class);
    $hold = $review->placeHold($line, $actor, 'Return risk', CommissionHoldType::ReturnRisk);
    $released = $review->releaseHold($hold, $actor, 'Return window passed');
    $dispute = $review->openDispute($line->fresh(), $actor, CommissionDisputeType::IncorrectRate, 'Rate is wrong', 'Expected higher rate');
    $resolved = $review->resolveDispute($dispute, $actor, CommissionDisputeStatus::Rejected, 'Original calculation stands');
    $line = $review->restoreLine($line->fresh(), $actor, 'Dispute rejected');
    $forfeiture = app(CommissionForfeitureService::class)->forfeit($line, '10.0000', 'MANUAL', 'Manual forfeiture', $actor);

    expect($hold->status)->toBe(CommissionHoldStatus::Active)
        ->and($released->status)->toBe(CommissionHoldStatus::Released)
        ->and($resolved->status)->toBe(CommissionDisputeStatus::Rejected)
        ->and($forfeiture->entry_type)->toBe(CommissionLedgerEntryType::Forfeiture)
        ->and((float) $forfeiture->amount)->toBe(-10.0)
        ->and((float) $line->fresh()->approved_amount)->toBe(90.0);

    $review->submitBatch($batch->fresh(), $actor);
    expect(fn () => $review->approveBatch($batch->fresh(), $actor))->toThrow(RuntimeException::class, 'Submitter cannot approve');

    $approver = commissionPhase5User();
    $approved = $review->approveBatch($batch->fresh(), $approver);
    $locked = $review->lockBatch($approved, $approver);

    expect($locked->status)->toBe(CommissionReviewBatchStatus::Locked)
        ->and($locked->lines()->where('review_status', CommissionReviewLineStatus::Approved)->exists())->toBeTrue();
});

test('settlement preparation creates source traceable allocations without payment or gl posting', function (): void {
    [$actor, $business, $referrer] = commissionPhase5Actor();
    $period = commissionPhase5OpenPeriod($actor, $business);
    commissionPhase5LedgerEntry($business, $referrer, '100.0000', 'NGN', 'INV-SETTLE');
    $batch = app(CommissionReviewBatchService::class)->generate($period, 'NGN', '2026-07-31', $actor);
    $review = app(CommissionReviewService::class);
    $review->submitBatch($batch, $actor);
    $approver = commissionPhase5User();
    $approved = $review->approveBatch($batch->fresh(), $approver);

    $paymentsBefore = Payment::query()->count();
    $glBefore = GlEntry::query()->count();
    $settlement = app(CommissionSettlementPreparationService::class)->prepare($approved, '2026-08-05', $actor);
    $sameSettlement = app(CommissionSettlementPreparationService::class)->prepare($approved, '2026-08-05', $actor);

    expect($sameSettlement->id)->toBe($settlement->id)
        ->and($settlement->currency_code)->toBe('NGN')
        ->and($settlement->lines)->toHaveCount(1)
        ->and($settlement->allocations)->toHaveCount(1)
        ->and((float) $settlement->total_net_amount)->toBe(100.0)
        ->and(Payment::query()->count())->toBe($paymentsBefore)
        ->and(GlEntry::query()->count())->toBe($glBefore);

    app(CommissionSettlementPreparationService::class)->submit($settlement, $actor);
    expect(fn () => app(CommissionSettlementPreparationService::class)->approve($settlement->fresh(), $actor))->toThrow(RuntimeException::class, 'Preparer or submitter cannot approve');

    $settlementApprover = commissionPhase5User();
    $locked = app(CommissionSettlementPreparationService::class)->lock(
        app(CommissionSettlementPreparationService::class)->approve($settlement->fresh(), $settlementApprover),
        $settlementApprover,
    );

    expect($locked->status)->toBe(CommissionSettlementBatchStatus::Locked);
});

test('approval balances are currency separated and reconcile remains report only', function (): void {
    [$actor, $business, $referrer] = commissionPhase5Actor();
    $ngn = commissionPhase5LedgerEntry($business, $referrer, '100.0000', 'NGN', 'INV-BAL-NGN');
    commissionPhase5LedgerEntry($business, $referrer, '15.0000', 'USD', 'INV-BAL-USD');

    $balances = app(ReferrerCommissionApprovalBalanceService::class)->balances(['referrer_id' => $referrer->id]);

    expect($balances['NGN']['open_accrual'])->toBe('100.0000')
        ->and($balances['USD']['open_accrual'])->toBe('15.0000');

    $before = [
        CommissionLedgerEntry::query()->count(),
        CommissionReviewPeriod::query()->count(),
        CommissionReviewBatch::query()->count(),
        CommissionReviewBatchLine::query()->count(),
        CommissionHold::query()->count(),
        CommissionDispute::query()->count(),
        CommissionSettlementBatch::query()->count(),
        CommissionSettlementAllocation::query()->count(),
        $ngn->fresh()->updated_at?->toDateTimeString(),
    ];
    $exportPath = storage_path('app/reports/commission-phase5-reconcile-test.json');
    File::delete($exportPath);

    Artisan::call('biwms:commission-reconcile', ['--details' => true, '--export' => $exportPath]);

    $after = [
        CommissionLedgerEntry::query()->count(),
        CommissionReviewPeriod::query()->count(),
        CommissionReviewBatch::query()->count(),
        CommissionReviewBatchLine::query()->count(),
        CommissionHold::query()->count(),
        CommissionDispute::query()->count(),
        CommissionSettlementBatch::query()->count(),
        CommissionSettlementAllocation::query()->count(),
        $ngn->fresh()->updated_at?->toDateTimeString(),
    ];

    expect($after)->toBe($before)
        ->and(File::exists($exportPath))->toBeTrue();
});

test('phase five architecture keeps payment gl manufacturing and customer shortcut out of scope', function (): void {
    expect(Schema::hasColumn('customers', 'referrer_id'))->toBeFalse()
        ->and(File::get(app_path('Services/Sales/ReferralCommissions/CommissionSettlementPreparationService.php')))
        ->not->toContain('Payment::create')
        ->not->toContain('GlEntry::create')
        ->and(File::get(app_path('Services/Sales/ReferralCommissions/CommissionReviewService.php')))
        ->not->toContain('Payment::create')
        ->not->toContain('GlEntry::create')
        ->and(File::exists(app_path('Services/Manufacturing/ManufacturingPhase2Service.php')))->toBeFalse();
});

test('backend authorization blocks unauthorized review generation', function (): void {
    $business = commissionPhase5Business();
    $user = User::factory()->create();
    $period = CommissionReviewPeriod::query()->create([
        'business_id' => $business->id,
        'code' => 'COMM-AUTH',
        'name' => 'Auth',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'status' => CommissionReviewPeriodStatus::Open,
    ]);

    expect(fn () => app(CommissionReviewBatchService::class)->generate($period, 'NGN', '2026-07-31', $user))
        ->toThrow(AuthorizationException::class);
});

function commissionPhase5Actor(): array
{
    $business = commissionPhase5Business();
    $referrer = Referrer::factory()->create([
        'business_id' => $business->id,
        'commission_eligible' => true,
        'is_active' => true,
    ]);

    return [commissionPhase5User(), $business, $referrer];
}

function commissionPhase5User(): User
{
    $user = User::factory()->create();
    foreach (commissionPhase5Permissions() as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $user->givePermissionTo(commissionPhase5Permissions());

    return $user;
}

function commissionPhase5Business(): Business
{
    return Business::query()->create([
        'code' => 'BIZ-P5-'.str()->random(6),
        'name' => 'Commission Phase 5 Business',
        'is_active' => true,
    ]);
}

function commissionPhase5OpenPeriod(User $actor, Business $business): CommissionReviewPeriod
{
    $service = app(CommissionReviewPeriodService::class);
    $period = $service->create([
        'business_id' => $business->id,
        'code' => 'COMM-P5-'.str()->random(5),
        'name' => 'Phase 5 Period',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
    ], $actor);

    return $service->open($period, $actor);
}

function commissionPhase5LedgerEntry(Business $business, Referrer $referrer, string $amount, string $currency = 'NGN', string $sourceNumber = 'INV-P5', string $postingDate = '2026-07-15'): CommissionLedgerEntry
{
    return CommissionLedgerEntry::query()->create([
        'business_id' => $business->id,
        'entry_type' => CommissionLedgerEntryType::Accrual,
        'referrer_id' => $referrer->id,
        'source_type' => Referrer::class,
        'source_id' => $referrer->id,
        'source_number' => $sourceNumber,
        'posting_date' => $postingDate,
        'currency_code' => $currency,
        'amount' => $amount,
        'base_amount' => $amount,
        'status' => CommissionLedgerEntryStatus::Open,
        'idempotency_key' => hash('sha256', 'phase5-ledger|'.$referrer->id.'|'.$sourceNumber.'|'.$currency.'|'.$amount),
    ]);
}

function commissionPhase5Permissions(): array
{
    return [
        'sales.commission_review_period.create',
        'sales.commission_review_period.open',
        'sales.commission_review_period.submit',
        'sales.commission_review_period.approve',
        'sales.commission_review_period.lock',
        'sales.commission_review_period.reopen',
        'sales.commission_review_period.cancel',
        'sales.commission_review_batch.generate',
        'sales.commission_review_batch.review',
        'sales.commission_review_batch.submit',
        'sales.commission_review_batch.approve',
        'sales.commission_review_batch.reject',
        'sales.commission_review_batch.lock',
        'sales.commission_hold.create',
        'sales.commission_hold.release',
        'sales.commission_dispute.create',
        'sales.commission_dispute.assign',
        'sales.commission_dispute.resolve',
        'sales.commission_forfeiture.create',
        'sales.commission_adjustment.create',
        'sales.commission_settlement_batch.prepare',
        'sales.commission_settlement_batch.submit',
        'sales.commission_settlement_batch.approve',
        'sales.commission_settlement_batch.reject',
        'sales.commission_settlement_batch.lock',
        'sales.commission_settlement_batch.cancel',
    ];
}
