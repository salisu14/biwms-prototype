<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionHoldStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CommissionReviewBatchStatus;
use App\Enums\CommissionReviewLineStatus;
use App\Enums\CommissionSettlementBatchStatus;
use App\Enums\CommissionSettlementLineStatus;
use App\Models\CommissionReviewBatch;
use App\Models\CommissionReviewBatchLine;
use App\Models\CommissionSettlementAllocation;
use App\Models\CommissionSettlementBatch;
use App\Models\CommissionSettlementLine;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Support\DecimalMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommissionSettlementPreparationService
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    public function prepare(CommissionReviewBatch $reviewBatch, string $settlementDate, User $actor, ?string $description = null, int $snapshotVersion = 1): CommissionSettlementBatch
    {
        $this->authorize($actor, 'sales.commission_settlement_batch.prepare');

        return DB::transaction(function () use ($reviewBatch, $settlementDate, $actor, $description, $snapshotVersion): CommissionSettlementBatch {
            $lockedReview = CommissionReviewBatch::query()->lockForUpdate()->findOrFail($reviewBatch->id);
            if (! in_array($lockedReview->status, [CommissionReviewBatchStatus::Approved, CommissionReviewBatchStatus::Locked], true)) {
                throw new \RuntimeException('Settlement preparation requires an approved or locked review batch.');
            }

            $idempotencyKey = hash('sha256', implode('|', [
                'commission-settlement-batch',
                $lockedReview->business_id ?? 'global',
                $lockedReview->id,
                $lockedReview->currency_code,
                $settlementDate,
                $snapshotVersion,
            ]));

            $batch = CommissionSettlementBatch::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($batch) {
                return $batch->fresh(['lines.allocations']);
            }

            $lines = $lockedReview->lines()
                ->where('review_status', CommissionReviewLineStatus::Approved)
                ->where('currency_code', $lockedReview->currency_code)
                ->where('approved_amount', '>', 0)
                ->whereDoesntHave('holds', fn ($query) => $query->where('status', CommissionHoldStatus::Active))
                ->whereDoesntHave('ledgerEntry.settlementAllocations')
                ->with('ledgerEntry')
                ->get();

            $batch = CommissionSettlementBatch::query()->create([
                'business_id' => $lockedReview->business_id,
                'settlement_number' => 'CSB-'.$lockedReview->batch_number.'-'.$snapshotVersion,
                'commission_review_period_id' => $lockedReview->commission_review_period_id,
                'commission_review_batch_id' => $lockedReview->id,
                'currency_code' => $lockedReview->currency_code,
                'status' => CommissionSettlementBatchStatus::Prepared,
                'settlement_date' => $settlementDate,
                'cutoff_date' => $lockedReview->cutoff_date,
                'description' => $description,
                'prepared_at' => now(),
                'prepared_by' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'snapshot_version' => $snapshotVersion,
                'metadata' => ['source_review_batch_status' => $lockedReview->status->value],
            ]);

            $this->createSettlementLines($batch, $lines);
            $this->recalculate($batch);
            $this->auditTrailService->recordGeneric('commission_settlement', 'settlement_prepared', $batch, userId: $actor->id);

            return $batch->fresh(['lines.allocations']);
        });
    }

    public function submit(CommissionSettlementBatch $batch, User $actor): CommissionSettlementBatch
    {
        $this->authorize($actor, 'sales.commission_settlement_batch.submit');

        return $this->transition($batch, [CommissionSettlementBatchStatus::Prepared, CommissionSettlementBatchStatus::Rejected], [
            'status' => CommissionSettlementBatchStatus::Submitted,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ], $actor, 'settlement_submitted');
    }

    public function approve(CommissionSettlementBatch $batch, User $actor): CommissionSettlementBatch
    {
        $this->authorize($actor, 'sales.commission_settlement_batch.approve');

        return DB::transaction(function () use ($batch, $actor): CommissionSettlementBatch {
            $locked = CommissionSettlementBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== CommissionSettlementBatchStatus::Submitted) {
                throw new \RuntimeException('Only submitted commission settlement batches can be approved.');
            }
            if ((int) $locked->prepared_by === (int) $actor->id || (int) $locked->submitted_by === (int) $actor->id) {
                throw new \RuntimeException('Preparer or submitter cannot approve their own commission settlement batch.');
            }

            $locked->forceFill([
                'status' => CommissionSettlementBatchStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();
            $locked->lines()->update(['status' => CommissionSettlementLineStatus::Approved->value]);
            $this->auditTrailService->recordGeneric('commission_settlement', 'settlement_approved', $locked, userId: $actor->id);

            return $locked->fresh(['lines']);
        });
    }

    public function reject(CommissionSettlementBatch $batch, User $actor, string $reason): CommissionSettlementBatch
    {
        $this->authorize($actor, 'sales.commission_settlement_batch.reject');
        if (blank($reason)) {
            throw new \RuntimeException('A settlement rejection reason is required.');
        }

        return $this->transition($batch, [CommissionSettlementBatchStatus::Submitted], [
            'status' => CommissionSettlementBatchStatus::Rejected,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ], $actor, 'settlement_rejected', ['reason' => $reason]);
    }

    public function lock(CommissionSettlementBatch $batch, User $actor): CommissionSettlementBatch
    {
        $this->authorize($actor, 'sales.commission_settlement_batch.lock');

        return DB::transaction(function () use ($batch, $actor): CommissionSettlementBatch {
            $locked = CommissionSettlementBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== CommissionSettlementBatchStatus::Approved) {
                throw new \RuntimeException('Only approved commission settlement batches can be locked.');
            }
            $locked->forceFill([
                'status' => CommissionSettlementBatchStatus::Locked,
                'locked_by' => $actor->id,
                'locked_at' => now(),
            ])->save();
            $locked->lines()->update(['status' => CommissionSettlementLineStatus::Locked->value]);
            $this->auditTrailService->recordGeneric('commission_settlement', 'settlement_locked', $locked, userId: $actor->id);

            return $locked->fresh(['lines.allocations']);
        });
    }

    public function cancel(CommissionSettlementBatch $batch, User $actor, string $reason): CommissionSettlementBatch
    {
        $this->authorize($actor, 'sales.commission_settlement_batch.cancel');
        if (blank($reason)) {
            throw new \RuntimeException('A settlement cancellation reason is required.');
        }

        return $this->transition($batch, [CommissionSettlementBatchStatus::Prepared, CommissionSettlementBatchStatus::Submitted, CommissionSettlementBatchStatus::Rejected], [
            'status' => CommissionSettlementBatchStatus::Cancelled,
        ], $actor, 'settlement_cancelled', ['reason' => $reason]);
    }

    public function recalculate(CommissionSettlementBatch $batch): CommissionSettlementBatch
    {
        $locked = CommissionSettlementBatch::query()->lockForUpdate()->findOrFail($batch->id);
        $lines = $locked->lines()->get();
        $locked->forceFill([
            'total_gross_amount' => DecimalMath::amount($lines->sum('gross_amount')),
            'total_hold_amount' => DecimalMath::amount($lines->sum('hold_amount')),
            'total_forfeiture_amount' => DecimalMath::amount($lines->sum('forfeiture_amount')),
            'total_adjustment_amount' => DecimalMath::amount($lines->sum('adjustment_amount')),
            'total_net_amount' => DecimalMath::amount($lines->sum('net_settlement_amount')),
            'referrer_count' => $lines->pluck('referrer_id')->unique()->count(),
            'line_count' => $lines->count(),
        ])->save();

        return $locked->fresh();
    }

    /**
     * @param  Collection<int, CommissionReviewBatchLine>  $reviewLines
     */
    private function createSettlementLines(CommissionSettlementBatch $batch, Collection $reviewLines): void
    {
        foreach ($reviewLines->groupBy(fn (CommissionReviewBatchLine $line): string => (string) $line->referrer_id) as $referrerId => $lines) {
            $netAmount = DecimalMath::amount($lines->sum('approved_amount'));
            $settlementLine = CommissionSettlementLine::query()->create([
                'business_id' => $batch->business_id,
                'commission_settlement_batch_id' => $batch->id,
                'commission_review_batch_id' => $batch->commission_review_batch_id,
                'referrer_id' => (int) $referrerId,
                'currency_code' => $batch->currency_code,
                'gross_amount' => $netAmount,
                'net_settlement_amount' => $netAmount,
                'status' => CommissionSettlementLineStatus::Prepared,
                'snapshot' => ['review_line_ids' => $lines->pluck('id')->all()],
                'idempotency_key' => hash('sha256', 'commission-settlement-line|'.$batch->id.'|'.$referrerId.'|'.$batch->currency_code),
            ]);

            foreach ($lines as $line) {
                CommissionSettlementAllocation::query()->firstOrCreate(
                    ['commission_ledger_entry_id' => $line->commission_ledger_entry_id],
                    [
                        'business_id' => $batch->business_id,
                        'commission_settlement_batch_id' => $batch->id,
                        'commission_settlement_line_id' => $settlementLine->id,
                        'allocated_amount' => $line->approved_amount,
                        'currency_code' => $line->currency_code,
                        'allocation_type' => $line->entry_type ?: CommissionLedgerEntryType::Accrual->value,
                        'idempotency_key' => hash('sha256', 'commission-settlement-allocation|'.$batch->id.'|'.$settlementLine->id.'|'.$line->commission_ledger_entry_id),
                    ],
                );
            }
        }
    }

    /**
     * @param  array<int, CommissionSettlementBatchStatus>  $allowedStatuses
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    private function transition(CommissionSettlementBatch $batch, array $allowedStatuses, array $attributes, User $actor, string $auditAction, array $metadata = []): CommissionSettlementBatch
    {
        return DB::transaction(function () use ($batch, $allowedStatuses, $attributes, $actor, $auditAction, $metadata): CommissionSettlementBatch {
            $locked = CommissionSettlementBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! in_array($locked->status, $allowedStatuses, true)) {
                throw new \RuntimeException("Commission settlement batch cannot transition from {$locked->status->value}.");
            }
            $locked->forceFill($attributes)->save();
            $this->auditTrailService->recordGeneric('commission_settlement', $auditAction, $locked, userId: $actor->id, metadata: $metadata);

            return $locked->fresh(['lines']);
        });
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("User is not authorized for {$permission}.");
        }
    }
}
