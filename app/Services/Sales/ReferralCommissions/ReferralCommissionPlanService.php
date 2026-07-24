<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\ReferralCommissionMethod;
use App\Enums\ReferralCommissionPlanStatus;
use App\Enums\ReferralCommissionScope;
use App\Models\ReferralCommissionPlan;
use App\Models\ReferralCommissionPlanTier;
use App\Services\AuditTrailService;
use App\Services\NumberSeriesService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReferralCommissionPlanService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
        private readonly NumberSeriesService $numberSeriesService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $actorId = null): ReferralCommissionPlan
    {
        $data = $this->normalize($data);
        $this->validateDraftPayload($data);

        return DB::transaction(function () use ($data, $actorId): ReferralCommissionPlan {
            $plan = ReferralCommissionPlan::query()->create([
                ...$data,
                'code' => $data['code'] ?? $this->numberSeriesService->getNextNo(ReferralCommissionPlanNumberSeriesSetupService::CODE),
                'status' => ReferralCommissionPlanStatus::DRAFT,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            if ($plan->is_default) {
                $this->clearOtherDefaults($plan);
            }

            $this->audit('referral_commission_plan_created', 'created', $plan, $actorId, newValues: $plan->getAttributes());

            return $plan;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(ReferralCommissionPlan $plan, array $data, ?int $actorId = null): ReferralCommissionPlan
    {
        return DB::transaction(function () use ($plan, $data, $actorId): ReferralCommissionPlan {
            $plan = ReferralCommissionPlan::query()->with(['tiers', 'eligibleItems', 'eligibleCategories'])->lockForUpdate()->findOrFail($plan->getKey());

            if ($plan->status !== ReferralCommissionPlanStatus::DRAFT) {
                throw new DomainException('Only draft commission plans can be structurally edited.');
            }

            $data = $this->normalize($data + $plan->only([
                'commission_method',
                'commission_scope',
                'effective_from',
            ]));
            $this->validateDraftPayload($data);

            $oldValues = $plan->getAttributes();
            $plan->update([...$data, 'updated_by' => $actorId]);

            if ($plan->is_default) {
                $this->clearOtherDefaults($plan);
            }

            $this->audit('referral_commission_plan_updated', 'updated', $plan, $actorId, oldValues: $oldValues, newValues: $plan->getAttributes());

            return $plan;
        });
    }

    public function activate(ReferralCommissionPlan $plan, ?int $actorId = null): ReferralCommissionPlan
    {
        return DB::transaction(function () use ($plan, $actorId): ReferralCommissionPlan {
            $plan = ReferralCommissionPlan::query()->with(['tiers', 'eligibleItems', 'eligibleCategories'])->lockForUpdate()->findOrFail($plan->getKey());
            $this->validateForActivation($plan);

            $plan->update([
                'status' => ReferralCommissionPlanStatus::ACTIVE,
                'activated_by' => $actorId,
                'activated_at' => now(),
                'updated_by' => $actorId,
            ]);

            if ($plan->is_default) {
                $this->clearOtherDefaults($plan);
            }

            $this->audit('referral_commission_plan_activated', 'activated', $plan, $actorId);

            return $plan;
        });
    }

    public function inactivate(ReferralCommissionPlan $plan, ?int $actorId = null): ReferralCommissionPlan
    {
        return DB::transaction(function () use ($plan, $actorId): ReferralCommissionPlan {
            $plan = ReferralCommissionPlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $plan->update([
                'status' => ReferralCommissionPlanStatus::INACTIVE,
                'is_default' => false,
                'inactivated_by' => $actorId,
                'inactivated_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->audit('referral_commission_plan_inactivated', 'inactivated', $plan, $actorId);

            return $plan;
        });
    }

    public function archive(ReferralCommissionPlan $plan, ?int $actorId = null): ReferralCommissionPlan
    {
        return DB::transaction(function () use ($plan, $actorId): ReferralCommissionPlan {
            $plan = ReferralCommissionPlan::query()->withCount('activeReferrerAssignments')->lockForUpdate()->findOrFail($plan->getKey());

            if ($plan->status === ReferralCommissionPlanStatus::ACTIVE) {
                throw new DomainException('Active commission plans cannot be archived.');
            }

            if ($plan->active_referrer_assignments_count > 0) {
                throw new DomainException('Commission plans with open active assignments cannot be archived.');
            }

            $plan->update([
                'status' => ReferralCommissionPlanStatus::ARCHIVED,
                'is_default' => false,
                'archived_by' => $actorId,
                'archived_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->audit('referral_commission_plan_archived', 'archived', $plan, $actorId);

            return $plan;
        });
    }

    public function validateForActivation(ReferralCommissionPlan $plan): void
    {
        $this->validateDraftPayload($plan->getAttributes());

        if ($plan->effective_to !== null && $plan->effective_to->lt($plan->effective_from)) {
            throw new DomainException('Effective To cannot be before Effective From.');
        }

        $method = $plan->commission_method;
        if ($method->isTiered()) {
            if ($plan->tier_basis === null) {
                throw new DomainException('Tier basis is required for tiered commission plans.');
            }

            if ($plan->tiers->isEmpty()) {
                throw new DomainException('Tiered commission plans require at least one tier before activation.');
            }

            $this->validateTiers($plan, $plan->tiers);
        } elseif ($plan->tiers->isNotEmpty()) {
            throw new DomainException('Non-tiered commission plans cannot retain tier rows.');
        }

        match ($plan->commission_scope) {
            ReferralCommissionScope::SPECIFIC_ITEMS => $this->assertHasIncludedRows($plan->eligibleItems, 'Specific item plans require at least one included item.'),
            ReferralCommissionScope::SPECIFIC_CATEGORIES => $this->assertHasIncludedRows($plan->eligibleCategories, 'Specific category plans require at least one included category.'),
            ReferralCommissionScope::SPECIFIC_ITEMS_AND_CATEGORIES => $this->assertHasAnyIncludedRows($plan),
            ReferralCommissionScope::ALL_ELIGIBLE_SALES => null,
        };
    }

    /**
     * @param  Collection<int, ReferralCommissionPlanTier>  $tiers
     */
    public function validateTiers(ReferralCommissionPlan $plan, Collection $tiers): void
    {
        $method = $plan->commission_method;
        $sorted = $tiers->sortBy('sequence')->values();
        $openEndedCount = 0;
        $previousMaximum = null;

        foreach ($sorted as $index => $tier) {
            $minimum = (float) $tier->minimum_threshold;
            $maximum = $tier->maximum_threshold === null ? null : (float) $tier->maximum_threshold;

            if ($minimum < 0) {
                throw new DomainException('Tier minimum threshold cannot be negative.');
            }

            if ($maximum !== null && $maximum <= $minimum) {
                throw new DomainException('Tier maximum threshold must exceed the minimum threshold.');
            }

            if ($previousMaximum !== null && $minimum < $previousMaximum) {
                throw new DomainException('Commission tier ranges cannot overlap.');
            }

            if ($maximum === null) {
                $openEndedCount++;
                if ($index !== $sorted->count() - 1) {
                    throw new DomainException('Only the final commission tier may be open-ended.');
                }
            }

            if ($method === ReferralCommissionMethod::TIERED_PERCENTAGE) {
                $this->assertPercentage($tier->percentage_rate, 'Percentage tier requires a rate between 0 and 100.');
                if ($tier->fixed_amount !== null) {
                    throw new DomainException('Percentage tiers cannot define fixed amounts.');
                }
            }

            if ($method === ReferralCommissionMethod::TIERED_FIXED_AMOUNT) {
                if ($tier->fixed_amount === null || (float) $tier->fixed_amount < 0) {
                    throw new DomainException('Fixed amount tier requires a non-negative fixed amount.');
                }
                if ($tier->percentage_rate !== null) {
                    throw new DomainException('Fixed amount tiers cannot define percentage rates.');
                }
            }

            $previousMaximum = $maximum;
        }

        if ($openEndedCount > 1) {
            throw new DomainException('Only one open-ended commission tier is allowed.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $method = $this->method($data['commission_method'] ?? ReferralCommissionMethod::PERCENTAGE);

        if ($method === ReferralCommissionMethod::PERCENTAGE) {
            $data['fixed_amount'] = null;
            $data['fixed_amount_application'] = null;
            $data['tier_basis'] = null;
        }

        if ($method === ReferralCommissionMethod::FIXED_AMOUNT) {
            $data['percentage_rate'] = null;
            $data['tier_basis'] = null;
        }

        if ($method === ReferralCommissionMethod::TIERED_PERCENTAGE) {
            $data['fixed_amount'] = null;
            $data['fixed_amount_application'] = null;
        }

        if ($method === ReferralCommissionMethod::TIERED_FIXED_AMOUNT) {
            $data['percentage_rate'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateDraftPayload(array $data): void
    {
        $method = $this->method($data['commission_method'] ?? ReferralCommissionMethod::PERCENTAGE);

        if (blank($data['name'] ?? null)) {
            throw new DomainException('Commission plan name is required.');
        }

        if (blank($data['effective_from'] ?? null)) {
            throw new DomainException('Effective From is required.');
        }

        if (($data['effective_to'] ?? null) !== null && (string) $data['effective_to'] < (string) $data['effective_from']) {
            throw new DomainException('Effective To cannot be before Effective From.');
        }

        if ($method === ReferralCommissionMethod::PERCENTAGE) {
            $this->assertPercentage($data['percentage_rate'] ?? null, 'Percentage plans require a rate greater than 0 and no more than 100.');
        }

        if ($method === ReferralCommissionMethod::FIXED_AMOUNT && (($data['fixed_amount'] ?? null) === null || (float) $data['fixed_amount'] < 0)) {
            throw new DomainException('Fixed amount plans require a non-negative fixed amount.');
        }

        if ($method->isTiered() && blank($data['tier_basis'] ?? null)) {
            throw new DomainException('Tier basis is required for tiered commission plans.');
        }

        foreach (['minimum_eligible_amount', 'maximum_commission_amount'] as $moneyField) {
            if (($data[$moneyField] ?? null) !== null && (float) $data[$moneyField] < 0) {
                throw new DomainException('Commission money amounts cannot be negative.');
            }
        }

        if (($method->isFixedAmount() || ($data['minimum_eligible_amount'] ?? null) !== null || ($data['maximum_commission_amount'] ?? null) !== null) && blank($data['currency_id'] ?? null)) {
            throw new DomainException('Currency is required for fixed amount or money-threshold commission plans.');
        }
    }

    private function assertPercentage(mixed $value, string $message): void
    {
        if ($value === null || (float) $value <= 0 || (float) $value > 100) {
            throw new DomainException($message);
        }
    }

    private function assertHasIncludedRows(Collection $rows, string $message): void
    {
        if (! $rows->contains(fn ($row): bool => $row->is_included === true)) {
            throw new DomainException($message);
        }
    }

    private function assertHasAnyIncludedRows(ReferralCommissionPlan $plan): void
    {
        if (
            ! $plan->eligibleItems->contains(fn ($row): bool => $row->is_included === true)
            && ! $plan->eligibleCategories->contains(fn ($row): bool => $row->is_included === true)
        ) {
            throw new DomainException('Specific item and category plans require at least one included item or category.');
        }
    }

    private function clearOtherDefaults(ReferralCommissionPlan $plan): void
    {
        ReferralCommissionPlan::query()
            ->whereKeyNot($plan->getKey())
            ->where('business_id', $plan->business_id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function method(mixed $method): ReferralCommissionMethod
    {
        return $method instanceof ReferralCommissionMethod ? $method : ReferralCommissionMethod::from((string) $method);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function audit(string $eventType, string $action, ReferralCommissionPlan $plan, ?int $actorId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: $eventType,
            action: $action,
            auditable: $plan,
            documentType: 'REFERRAL_COMMISSION_PLAN',
            documentNo: $plan->code,
            userId: $actorId,
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: ['business_id' => $plan->business_id],
        );
    }
}
