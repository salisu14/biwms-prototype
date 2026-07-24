<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\ReferralCommissionAssignmentStatus;
use App\Enums\ReferralCommissionPlanStatus;
use App\Models\ReferralCommissionPlan;
use App\Models\Referrer;
use App\Models\ReferrerCommissionPlanAssignment;
use App\Services\AuditTrailService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReferrerCommissionPlanAssignmentService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function assign(Referrer $referrer, ReferralCommissionPlan $plan, array $data, ?int $actorId = null): ReferrerCommissionPlanAssignment
    {
        return DB::transaction(function () use ($referrer, $plan, $data, $actorId): ReferrerCommissionPlanAssignment {
            $referrer = Referrer::query()->lockForUpdate()->findOrFail($referrer->getKey());
            $plan = ReferralCommissionPlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $effectiveFrom = $data['effective_from'] ?? today();

            $this->assertAssignable($referrer, $plan, $effectiveFrom);

            if (($data['is_primary'] ?? true) === true) {
                $this->assertNoOpenActivePrimary($referrer);
            }

            $assignment = ReferrerCommissionPlanAssignment::query()->create([
                'business_id' => $data['business_id'] ?? $plan->business_id ?? $referrer->business_id,
                'referrer_id' => $referrer->getKey(),
                'referral_commission_plan_id' => $plan->getKey(),
                'status' => ReferralCommissionAssignmentStatus::ACTIVE,
                'effective_from' => $effectiveFrom,
                'effective_to' => $data['effective_to'] ?? null,
                'is_primary' => $data['is_primary'] ?? true,
                'assignment_reason' => $data['assignment_reason'] ?? null,
                'assigned_by' => $actorId,
                'assigned_at' => now(),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->audit('referrer_commission_plan_assigned', 'assigned', $assignment, $actorId, [
                'referrer_id' => $referrer->getKey(),
                'new_plan_id' => $plan->getKey(),
                'effective_from' => (string) $assignment->effective_from?->toDateString(),
            ]);

            return $assignment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function change(Referrer $referrer, ReferralCommissionPlan $newPlan, array $data, ?int $actorId = null): ReferrerCommissionPlanAssignment
    {
        $reason = trim((string) ($data['reason'] ?? $data['assignment_reason'] ?? ''));

        if ($reason === '') {
            throw new DomainException('A reason is required when changing a referrer commission plan.');
        }

        return DB::transaction(function () use ($referrer, $newPlan, $data, $actorId, $reason): ReferrerCommissionPlanAssignment {
            $referrer = Referrer::query()->lockForUpdate()->findOrFail($referrer->getKey());
            $newPlan = ReferralCommissionPlan::query()->lockForUpdate()->findOrFail($newPlan->getKey());
            $current = $this->openActivePrimaryQuery($referrer)->lockForUpdate()->first();
            $effectiveFrom = $data['effective_from'] ?? today();

            $this->assertAssignable($referrer, $newPlan, $effectiveFrom);

            if ($current) {
                $current->update([
                    'status' => ReferralCommissionAssignmentStatus::ENDED,
                    'effective_to' => $this->dateString($data['effective_to'] ?? $effectiveFrom),
                    'ended_by' => $actorId,
                    'ended_at' => now(),
                    'end_reason' => $reason,
                    'updated_by' => $actorId,
                ]);
            }

            $newAssignment = $this->assign($referrer, $newPlan, [
                ...$data,
                'is_primary' => true,
                'effective_from' => $effectiveFrom,
                'assignment_reason' => $reason,
            ], $actorId);

            $this->audit('referrer_commission_plan_changed', 'changed', $newAssignment, $actorId, [
                'referrer_id' => $referrer->getKey(),
                'old_plan_id' => $current?->referral_commission_plan_id,
                'new_plan_id' => $newPlan->getKey(),
                'effective_from' => (string) $newAssignment->effective_from?->toDateString(),
                'reason' => $reason,
            ]);

            return $newAssignment;
        });
    }

    public function end(ReferrerCommissionPlanAssignment $assignment, string $reason, ?CarbonInterface $effectiveTo = null, ?int $actorId = null): ReferrerCommissionPlanAssignment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('An end reason is required.');
        }

        return DB::transaction(function () use ($assignment, $reason, $effectiveTo, $actorId): ReferrerCommissionPlanAssignment {
            $assignment = ReferrerCommissionPlanAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $assignment->update([
                'status' => ReferralCommissionAssignmentStatus::ENDED,
                'effective_to' => $effectiveTo ?? today(),
                'ended_by' => $actorId,
                'ended_at' => now(),
                'end_reason' => $reason,
                'updated_by' => $actorId,
            ]);

            $this->audit('referrer_commission_plan_assignment_ended', 'ended', $assignment, $actorId, ['reason' => $reason]);

            return $assignment;
        });
    }

    public function cancel(ReferrerCommissionPlanAssignment $assignment, string $reason, ?int $actorId = null): ReferrerCommissionPlanAssignment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($assignment, $reason, $actorId): ReferrerCommissionPlanAssignment {
            $assignment = ReferrerCommissionPlanAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $assignment->update([
                'status' => ReferralCommissionAssignmentStatus::CANCELLED,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $actorId,
            ]);

            $this->audit('referrer_commission_plan_assignment_cancelled', 'cancelled', $assignment, $actorId, ['reason' => $reason]);

            return $assignment;
        });
    }

    private function assertAssignable(Referrer $referrer, ReferralCommissionPlan $plan, mixed $effectiveFrom): void
    {
        if (! $referrer->is_active) {
            throw new DomainException('Inactive referrers cannot receive commission plan assignments.');
        }

        if (! $referrer->commission_eligible) {
            throw new DomainException('Commission-ineligible referrers cannot receive commission plan assignments.');
        }

        if ($plan->status !== ReferralCommissionPlanStatus::ACTIVE) {
            throw new DomainException('Only active commission plans can be assigned.');
        }

        $effectiveDate = $effectiveFrom instanceof CarbonInterface ? $effectiveFrom : Carbon::parse($effectiveFrom);
        if (! $plan->isCurrentlyEffective($effectiveDate)) {
            throw new DomainException('Commission plan is not effective on the assignment date.');
        }

        if ($referrer->business_id !== null && $plan->business_id !== null && (int) $referrer->business_id !== (int) $plan->business_id) {
            throw new DomainException('Referrer and commission plan must belong to the same business.');
        }
    }

    private function assertNoOpenActivePrimary(Referrer $referrer, ?ReferrerCommissionPlanAssignment $except = null): void
    {
        $query = $this->openActivePrimaryQuery($referrer);

        if ($except) {
            $query->whereKeyNot($except->getKey());
        }

        if ($query->exists()) {
            throw new DomainException('Referrer already has an active primary commission plan assignment.');
        }
    }

    private function openActivePrimaryQuery(Referrer $referrer)
    {
        return ReferrerCommissionPlanAssignment::query()
            ->where('referrer_id', $referrer->getKey())
            ->where('is_primary', true)
            ->where('status', ReferralCommissionAssignmentStatus::ACTIVE)
            ->whereNull('effective_to');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $eventType, string $action, ReferrerCommissionPlanAssignment $assignment, ?int $actorId = null, array $metadata = []): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: $eventType,
            action: $action,
            auditable: $assignment,
            documentType: 'REFERRER_COMMISSION_PLAN_ASSIGNMENT',
            documentNo: (string) $assignment->getKey(),
            userId: $actorId,
            metadata: [
                'business_id' => $assignment->business_id,
                'referrer_id' => $assignment->referrer_id,
                'plan_id' => $assignment->referral_commission_plan_id,
                ...$metadata,
            ],
        );
    }

    private function dateString(mixed $date): mixed
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : $date;
    }
}
