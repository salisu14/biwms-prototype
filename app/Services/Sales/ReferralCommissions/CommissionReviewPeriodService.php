<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionReviewPeriodStatus;
use App\Models\CommissionReviewPeriod;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CommissionReviewPeriodService
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): CommissionReviewPeriod
    {
        $this->authorize($actor, 'sales.commission_review_period.create');

        return DB::transaction(function () use ($data, $actor): CommissionReviewPeriod {
            $this->assertValidDates($data['period_start'], $data['period_end']);
            $this->assertNoOverlap($data['period_start'], $data['period_end'], $data['business_id'] ?? null);

            $period = CommissionReviewPeriod::query()->create([
                ...$data,
                'status' => CommissionReviewPeriodStatus::Draft,
                'currency_mode' => $data['currency_mode'] ?? 'separate',
            ]);

            $this->auditTrailService->recordGeneric('commission_review', 'period_created', $period, userId: $actor->id);

            return $period;
        });
    }

    public function open(CommissionReviewPeriod $period, User $actor): CommissionReviewPeriod
    {
        $this->authorize($actor, 'sales.commission_review_period.open');

        return $this->transition($period, [CommissionReviewPeriodStatus::Draft, CommissionReviewPeriodStatus::Reopened], [
            'status' => CommissionReviewPeriodStatus::Open,
            'opened_by' => $actor->id,
            'opened_at' => now(),
        ], $actor, 'period_opened');
    }

    public function submit(CommissionReviewPeriod $period, User $actor): CommissionReviewPeriod
    {
        $this->authorize($actor, 'sales.commission_review_period.submit');

        return $this->transition($period, [CommissionReviewPeriodStatus::Open, CommissionReviewPeriodStatus::UnderReview], [
            'status' => CommissionReviewPeriodStatus::Submitted,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ], $actor, 'period_submitted');
    }

    public function approve(CommissionReviewPeriod $period, User $actor): CommissionReviewPeriod
    {
        $this->authorize($actor, 'sales.commission_review_period.approve');

        return $this->transition($period, [CommissionReviewPeriodStatus::Submitted], [
            'status' => CommissionReviewPeriodStatus::Approved,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ], $actor, 'period_approved');
    }

    public function lock(CommissionReviewPeriod $period, User $actor): CommissionReviewPeriod
    {
        $this->authorize($actor, 'sales.commission_review_period.lock');

        return $this->transition($period, [CommissionReviewPeriodStatus::Approved], [
            'status' => CommissionReviewPeriodStatus::Locked,
            'locked_by' => $actor->id,
            'locked_at' => now(),
        ], $actor, 'period_locked');
    }

    public function reopen(CommissionReviewPeriod $period, User $actor, string $reason): CommissionReviewPeriod
    {
        $this->authorize($actor, 'sales.commission_review_period.reopen');
        if (blank($reason)) {
            throw new \RuntimeException('A reopen reason is required.');
        }

        return $this->transition($period, [CommissionReviewPeriodStatus::Locked, CommissionReviewPeriodStatus::Approved], [
            'status' => CommissionReviewPeriodStatus::Reopened,
            'reopened_by' => $actor->id,
            'reopened_at' => now(),
            'reopen_reason' => $reason,
        ], $actor, 'period_reopened', ['reason' => $reason]);
    }

    public function cancel(CommissionReviewPeriod $period, User $actor, string $reason): CommissionReviewPeriod
    {
        $this->authorize($actor, 'sales.commission_review_period.cancel');
        if (blank($reason)) {
            throw new \RuntimeException('A cancellation reason is required.');
        }

        return $this->transition($period, [CommissionReviewPeriodStatus::Draft, CommissionReviewPeriodStatus::Open, CommissionReviewPeriodStatus::UnderReview, CommissionReviewPeriodStatus::Submitted], [
            'status' => CommissionReviewPeriodStatus::Cancelled,
        ], $actor, 'period_cancelled', ['reason' => $reason]);
    }

    /**
     * @param  array<int, CommissionReviewPeriodStatus>  $allowedStatuses
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    private function transition(CommissionReviewPeriod $period, array $allowedStatuses, array $attributes, User $actor, string $auditAction, array $metadata = []): CommissionReviewPeriod
    {
        return DB::transaction(function () use ($period, $allowedStatuses, $attributes, $actor, $auditAction, $metadata): CommissionReviewPeriod {
            $locked = CommissionReviewPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (! in_array($locked->status, $allowedStatuses, true)) {
                throw new \RuntimeException("Commission review period cannot transition from {$locked->status->value}.");
            }

            $oldStatus = $locked->status->value;
            $locked->forceFill($attributes)->save();

            $this->auditTrailService->recordGeneric('commission_review', $auditAction, $locked, userId: $actor->id, metadata: [
                'old_status' => $oldStatus,
                'new_status' => $locked->status->value,
                ...$metadata,
            ]);

            return $locked->fresh();
        });
    }

    private function assertNoOverlap(string $periodStart, string $periodEnd, ?int $businessId): void
    {
        $exists = CommissionReviewPeriod::query()
            ->where('business_id', $businessId)
            ->whereNot('status', CommissionReviewPeriodStatus::Cancelled)
            ->whereDate('period_start', '<=', $periodEnd)
            ->whereDate('period_end', '>=', $periodStart)
            ->exists();

        if ($exists) {
            throw new \RuntimeException('Commission review period date range overlaps an existing period.');
        }
    }

    private function assertValidDates(string $periodStart, string $periodEnd): void
    {
        if ($periodStart > $periodEnd) {
            throw new \RuntimeException('Commission review period start date must be before or equal to end date.');
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("User is not authorized for {$permission}.");
        }
    }
}
