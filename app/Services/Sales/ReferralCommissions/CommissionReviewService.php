<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionDisputeStatus;
use App\Enums\CommissionDisputeType;
use App\Enums\CommissionHoldStatus;
use App\Enums\CommissionHoldType;
use App\Enums\CommissionReviewBatchStatus;
use App\Enums\CommissionReviewLineStatus;
use App\Models\CommissionDispute;
use App\Models\CommissionHold;
use App\Models\CommissionReviewBatch;
use App\Models\CommissionReviewBatchLine;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CommissionReviewService
{
    public function __construct(
        private readonly CommissionReviewBatchService $batchService,
        private readonly CommissionAdjustmentService $adjustmentService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function markEligible(CommissionReviewBatchLine $line, User $actor, ?string $note = null): CommissionReviewBatchLine
    {
        $this->authorize($actor, 'sales.commission_review_batch.review');

        return $this->updateLine($line, $actor, [
            'review_status' => CommissionReviewLineStatus::Eligible,
            'approved_amount' => DecimalMath::sub($line->eligible_amount, $line->held_amount, DecimalPrecision::AMOUNT_SCALE),
            'review_notes' => $note,
        ], 'line_marked_eligible');
    }

    public function rejectLine(CommissionReviewBatchLine $line, User $actor, string $reason): CommissionReviewBatchLine
    {
        $this->authorize($actor, 'sales.commission_review_batch.review');
        if (blank($reason)) {
            throw new \RuntimeException('A rejection reason is required.');
        }

        return $this->updateLine($line, $actor, [
            'review_status' => CommissionReviewLineStatus::Rejected,
            'approved_amount' => '0.0000',
            'exception_code' => 'rejected',
            'exception_message' => $reason,
        ], 'line_rejected', ['reason' => $reason]);
    }

    public function restoreLine(CommissionReviewBatchLine $line, User $actor, string $reason): CommissionReviewBatchLine
    {
        $this->authorize($actor, 'sales.commission_review_batch.review');
        if (blank($reason)) {
            throw new \RuntimeException('A restore reason is required.');
        }

        return $this->updateLine($line, $actor, [
            'review_status' => CommissionReviewLineStatus::Eligible,
            'approved_amount' => DecimalMath::sub($line->eligible_amount, $line->held_amount, DecimalPrecision::AMOUNT_SCALE),
            'exception_code' => null,
            'exception_message' => null,
        ], 'line_restored', ['reason' => $reason]);
    }

    public function raiseException(CommissionReviewBatchLine $line, User $actor, string $code, string $message): CommissionReviewBatchLine
    {
        $this->authorize($actor, 'sales.commission_review_batch.review');

        return $this->updateLine($line, $actor, [
            'exception_status' => 'open',
            'exception_code' => $code,
            'exception_message' => $message,
        ], 'line_exception_raised');
    }

    public function clearException(CommissionReviewBatchLine $line, User $actor, string $reason): CommissionReviewBatchLine
    {
        $this->authorize($actor, 'sales.commission_review_batch.review');

        return $this->updateLine($line, $actor, [
            'exception_status' => null,
            'exception_code' => null,
            'exception_message' => null,
        ], 'line_exception_cleared', ['reason' => $reason]);
    }

    public function placeHold(CommissionReviewBatchLine $line, User $actor, string $reason, CommissionHoldType $holdType = CommissionHoldType::Manual, ?string $amount = null, ?string $reasonCode = null): CommissionHold
    {
        $this->authorize($actor, 'sales.commission_hold.create');
        if (blank($reason)) {
            throw new \RuntimeException('A hold reason is required.');
        }

        return DB::transaction(function () use ($line, $actor, $reason, $holdType, $amount, $reasonCode): CommissionHold {
            $locked = CommissionReviewBatchLine::query()->lockForUpdate()->findOrFail($line->id);
            $this->assertBatchEditable($locked->batch);
            $holdAmount = DecimalMath::amount($amount ?? $locked->approved_amount);
            if (DecimalMath::compare($holdAmount, $locked->approved_amount) > 0) {
                throw new \RuntimeException('Commission hold amount cannot exceed currently approved amount.');
            }

            $hold = CommissionHold::query()->firstOrCreate(
                ['idempotency_key' => hash('sha256', 'commission-hold|'.$locked->id.'|'.$holdAmount.'|'.$reasonCode.'|'.$reason)],
                [
                    'business_id' => $locked->business_id,
                    'referrer_id' => $locked->referrer_id,
                    'commission_review_batch_id' => $locked->commission_review_batch_id,
                    'commission_review_batch_line_id' => $locked->id,
                    'commission_ledger_entry_id' => $locked->commission_ledger_entry_id,
                    'hold_type' => $holdType,
                    'status' => CommissionHoldStatus::Active,
                    'amount' => $holdAmount,
                    'currency_code' => $locked->currency_code,
                    'reason_code' => $reasonCode,
                    'reason' => $reason,
                    'placed_at' => now(),
                    'placed_by' => $actor->id,
                ],
            );

            $this->refreshLineAmounts($locked);
            $locked->forceFill(['review_status' => CommissionReviewLineStatus::Held])->save();
            $this->batchService->recalculate($locked->batch);
            $this->auditTrailService->recordGeneric('commission_review', 'hold_placed', $hold, userId: $actor->id, metadata: ['reason' => $reason]);

            return $hold->fresh();
        });
    }

    public function releaseHold(CommissionHold $hold, User $actor, string $reason): CommissionHold
    {
        $this->authorize($actor, 'sales.commission_hold.release');
        if (blank($reason)) {
            throw new \RuntimeException('A hold release reason is required.');
        }

        return DB::transaction(function () use ($hold, $actor, $reason): CommissionHold {
            $locked = CommissionHold::query()->lockForUpdate()->findOrFail($hold->id);
            if ($locked->status !== CommissionHoldStatus::Released) {
                $locked->forceFill([
                    'status' => CommissionHoldStatus::Released,
                    'released_at' => now(),
                    'released_by' => $actor->id,
                    'release_reason' => $reason,
                ])->save();
            }

            if ($locked->line) {
                $this->refreshLineAmounts($locked->line);
                $this->batchService->recalculate($locked->line->batch);
            }

            $this->auditTrailService->recordGeneric('commission_review', 'hold_released', $locked, userId: $actor->id, metadata: ['reason' => $reason]);

            return $locked->fresh();
        });
    }

    public function openDispute(CommissionReviewBatchLine $line, User $actor, CommissionDisputeType $type, string $subject, string $description, ?string $claimedAmount = null): CommissionDispute
    {
        $this->authorize($actor, 'sales.commission_dispute.create');

        return DB::transaction(function () use ($line, $actor, $type, $subject, $description, $claimedAmount): CommissionDispute {
            $locked = CommissionReviewBatchLine::query()->lockForUpdate()->findOrFail($line->id);
            $this->assertBatchEditable($locked->batch);
            $dispute = CommissionDispute::query()->firstOrCreate(
                ['idempotency_key' => hash('sha256', 'commission-dispute|'.$locked->id.'|'.$type->value.'|'.$subject)],
                [
                    'business_id' => $locked->business_id,
                    'dispute_number' => 'CDP-'.$locked->id.'-'.now()->format('YmdHis'),
                    'referrer_id' => $locked->referrer_id,
                    'commission_review_period_id' => $locked->batch->commission_review_period_id,
                    'commission_review_batch_id' => $locked->commission_review_batch_id,
                    'commission_review_batch_line_id' => $locked->id,
                    'commission_ledger_entry_id' => $locked->commission_ledger_entry_id,
                    'source_type' => $locked->source_type,
                    'source_id' => $locked->source_id,
                    'status' => CommissionDisputeStatus::Open,
                    'dispute_type' => $type,
                    'claimed_amount' => DecimalMath::amount($claimedAmount ?? $locked->eligible_amount),
                    'currency_code' => $locked->currency_code,
                    'subject' => $subject,
                    'description' => $description,
                    'raised_at' => now(),
                    'raised_by' => $actor->id,
                ],
            );

            $locked->forceFill([
                'review_status' => CommissionReviewLineStatus::Disputed,
                'exception_status' => 'open',
                'exception_code' => 'open_dispute',
                'exception_message' => $subject,
                'approved_amount' => '0.0000',
            ])->save();
            $this->batchService->recalculate($locked->batch);
            $this->auditTrailService->recordGeneric('commission_review', 'dispute_created', $dispute, userId: $actor->id);

            return $dispute->fresh();
        });
    }

    public function assignDispute(CommissionDispute $dispute, User $actor, User $assignee): CommissionDispute
    {
        $this->authorize($actor, 'sales.commission_dispute.assign');

        return DB::transaction(function () use ($dispute, $actor, $assignee): CommissionDispute {
            $locked = CommissionDispute::query()->lockForUpdate()->findOrFail($dispute->id);
            $locked->forceFill([
                'status' => CommissionDisputeStatus::UnderReview,
                'assigned_to' => $assignee->id,
            ])->save();
            $this->auditTrailService->recordGeneric('commission_review', 'dispute_assigned', $locked, userId: $actor->id, metadata: ['assigned_to' => $assignee->id]);

            return $locked->fresh();
        });
    }

    public function resolveDispute(CommissionDispute $dispute, User $actor, CommissionDisputeStatus $outcome, string $resolution, ?string $adjustmentAmount = null): CommissionDispute
    {
        $this->authorize($actor, 'sales.commission_dispute.resolve');
        if (blank($resolution)) {
            throw new \RuntimeException('A dispute resolution is required.');
        }

        return DB::transaction(function () use ($dispute, $actor, $outcome, $resolution, $adjustmentAmount): CommissionDispute {
            $locked = CommissionDispute::query()->lockForUpdate()->findOrFail($dispute->id);
            if ($locked->resolved_at !== null) {
                return $locked->fresh();
            }

            $adjustment = null;
            if (in_array($outcome, [CommissionDisputeStatus::Upheld, CommissionDisputeStatus::PartiallyUpheld], true)) {
                $adjustment = $this->adjustmentService->create(
                    referrer: $locked->line->referrer,
                    postingDate: now(),
                    currencyCode: $locked->currency_code,
                    amount: DecimalMath::amount($adjustmentAmount ?? $locked->claimed_amount),
                    reasonCode: 'DISPUTE',
                    description: $resolution,
                    actor: $actor,
                    idempotencyKey: hash('sha256', 'commission-dispute-adjustment|'.$locked->id.'|'.$outcome->value),
                );
            }

            $locked->forceFill([
                'status' => $outcome,
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'resolution' => $resolution,
                'resolution_code' => $outcome->value,
                'approved_adjustment_id' => $adjustment?->id,
            ])->save();

            if ($locked->line) {
                $this->clearException($locked->line, $actor, $resolution);
            }

            $this->auditTrailService->recordGeneric('commission_review', 'dispute_resolved', $locked, userId: $actor->id, metadata: ['outcome' => $outcome->value]);

            return $locked->fresh();
        });
    }

    public function submitBatch(CommissionReviewBatch $batch, User $actor): CommissionReviewBatch
    {
        $this->authorize($actor, 'sales.commission_review_batch.submit');

        return $this->batchTransition($batch, [CommissionReviewBatchStatus::Generated, CommissionReviewBatchStatus::UnderReview, CommissionReviewBatchStatus::Rejected], [
            'status' => CommissionReviewBatchStatus::Submitted,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ], $actor, 'batch_submitted');
    }

    public function approveBatch(CommissionReviewBatch $batch, User $actor): CommissionReviewBatch
    {
        $this->authorize($actor, 'sales.commission_review_batch.approve');

        return DB::transaction(function () use ($batch, $actor): CommissionReviewBatch {
            $locked = CommissionReviewBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== CommissionReviewBatchStatus::Submitted) {
                throw new \RuntimeException('Only submitted commission review batches can be approved.');
            }
            if ((int) $locked->submitted_by === (int) $actor->id) {
                throw new \RuntimeException('Submitter cannot approve their own commission review batch.');
            }
            if ($locked->lines()->whereIn('review_status', [CommissionReviewLineStatus::Pending, CommissionReviewLineStatus::Disputed])->exists()) {
                throw new \RuntimeException('Commission review batch has pending or disputed lines.');
            }
            if ($locked->holds()->where('status', CommissionHoldStatus::Active)->exists()) {
                throw new \RuntimeException('Commission review batch has active holds.');
            }

            $locked->forceFill([
                'status' => CommissionReviewBatchStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();
            $locked->lines()->where('review_status', CommissionReviewLineStatus::Eligible)->update([
                'review_status' => CommissionReviewLineStatus::Approved->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            $this->batchService->recalculate($locked);
            $this->auditTrailService->recordGeneric('commission_review', 'batch_approved', $locked, userId: $actor->id);

            return $locked->fresh(['lines']);
        });
    }

    public function rejectBatch(CommissionReviewBatch $batch, User $actor, string $reason): CommissionReviewBatch
    {
        $this->authorize($actor, 'sales.commission_review_batch.reject');
        if (blank($reason)) {
            throw new \RuntimeException('A rejection reason is required.');
        }

        return $this->batchTransition($batch, [CommissionReviewBatchStatus::Submitted], [
            'status' => CommissionReviewBatchStatus::Rejected,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ], $actor, 'batch_rejected', ['reason' => $reason]);
    }

    public function lockBatch(CommissionReviewBatch $batch, User $actor): CommissionReviewBatch
    {
        $this->authorize($actor, 'sales.commission_review_batch.lock');

        return $this->batchTransition($batch, [CommissionReviewBatchStatus::Approved], [
            'status' => CommissionReviewBatchStatus::Locked,
            'locked_by' => $actor->id,
            'locked_at' => now(),
        ], $actor, 'batch_locked');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    private function updateLine(CommissionReviewBatchLine $line, User $actor, array $attributes, string $auditAction, array $metadata = []): CommissionReviewBatchLine
    {
        return DB::transaction(function () use ($line, $actor, $attributes, $auditAction, $metadata): CommissionReviewBatchLine {
            $locked = CommissionReviewBatchLine::query()->lockForUpdate()->findOrFail($line->id);
            $this->assertBatchEditable($locked->batch);
            $locked->forceFill([
                ...$attributes,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();
            $this->batchService->recalculate($locked->batch);
            $this->auditTrailService->recordGeneric('commission_review', $auditAction, $locked, userId: $actor->id, metadata: $metadata);

            return $locked->fresh();
        });
    }

    private function refreshLineAmounts(CommissionReviewBatchLine $line): void
    {
        $activeHoldAmount = $line->holds()->where('status', CommissionHoldStatus::Active)->sum('amount');
        $approvedAmount = DecimalMath::sub($line->eligible_amount, $activeHoldAmount, DecimalPrecision::AMOUNT_SCALE);
        if (DecimalMath::compare($approvedAmount, 0) < 0) {
            $approvedAmount = '0.0000';
        }

        $line->forceFill([
            'held_amount' => DecimalMath::amount($activeHoldAmount),
            'approved_amount' => $approvedAmount,
            'review_status' => DecimalMath::compare($activeHoldAmount, 0) > 0 ? CommissionReviewLineStatus::Held : CommissionReviewLineStatus::Eligible,
        ])->save();
    }

    private function assertBatchEditable(?CommissionReviewBatch $batch): void
    {
        if (! $batch || in_array($batch->status, [CommissionReviewBatchStatus::Approved, CommissionReviewBatchStatus::Locked, CommissionReviewBatchStatus::Cancelled, CommissionReviewBatchStatus::Superseded], true)) {
            throw new \RuntimeException('Commission review batch is not editable.');
        }
    }

    /**
     * @param  array<int, CommissionReviewBatchStatus>  $allowedStatuses
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    private function batchTransition(CommissionReviewBatch $batch, array $allowedStatuses, array $attributes, User $actor, string $auditAction, array $metadata = []): CommissionReviewBatch
    {
        return DB::transaction(function () use ($batch, $allowedStatuses, $attributes, $actor, $auditAction, $metadata): CommissionReviewBatch {
            $locked = CommissionReviewBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! in_array($locked->status, $allowedStatuses, true)) {
                throw new \RuntimeException("Commission review batch cannot transition from {$locked->status->value}.");
            }
            $locked->forceFill($attributes)->save();
            $this->auditTrailService->recordGeneric('commission_review', $auditAction, $locked, userId: $actor->id, metadata: $metadata);

            return $locked->fresh();
        });
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("User is not authorized for {$permission}.");
        }
    }
}
