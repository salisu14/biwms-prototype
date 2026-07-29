<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CustomerReferralStatus;
use App\Enums\ReferralCommissionAssignmentStatus;
use App\Enums\ReferralCommissionPlanStatus;
use App\Enums\ReferralCommissionScope;
use App\Models\CustomerReferral;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\ReferralCommissionPlan;
use App\Models\ReferralCommissionPlanTier;
use App\Models\ReferrerCommissionPlanAssignment;
use App\Support\DecimalMath;
use Illuminate\Support\Carbon;

class CommissionEligibilityService
{
    /**
     * @return array{eligible: bool, reason_code: string|null, reason_message: string|null, customer_referral: CustomerReferral|null, assignment: ReferrerCommissionPlanAssignment|null, plan: ReferralCommissionPlan|null}
     */
    public function resolveHeader(PostedSalesInvoice $invoice): array
    {
        $postingDate = Carbon::parse($invoice->posting_date);
        $referral = CustomerReferral::query()
            ->with('referrer')
            ->where('customer_id', $invoice->customer_id)
            ->where('is_primary', true)
            ->where('status', CustomerReferralStatus::ACTIVE)
            ->whereDate('effective_from', '<=', $postingDate->toDateString())
            ->where(function ($query) use ($postingDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $postingDate->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();

        if (! $referral) {
            return $this->ineligibleHeader('referral_missing', 'No active primary referral was effective on the posted invoice date.');
        }

        if (! $referral->referrer?->is_active || ! $referral->referrer?->commission_eligible) {
            return $this->ineligibleHeader('referrer_ineligible', 'The referrer is inactive or not commission eligible.', $referral);
        }

        $assignment = ReferrerCommissionPlanAssignment::query()
            ->with('plan.tiers', 'plan.eligibleItems', 'plan.eligibleCategories')
            ->where('referrer_id', $referral->referrer_id)
            ->where('status', ReferralCommissionAssignmentStatus::ACTIVE)
            ->whereDate('effective_from', '<=', $postingDate->toDateString())
            ->where(function ($query) use ($postingDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $postingDate->toDateString());
            })
            ->orderByDesc('is_primary')
            ->orderByDesc('effective_from')
            ->first();

        if (! $assignment) {
            return $this->ineligibleHeader('plan_missing', 'No active commission plan assignment was effective for the referrer.', $referral);
        }

        $plan = $assignment->plan;
        if (! $plan || $plan->status !== ReferralCommissionPlanStatus::ACTIVE) {
            return $this->ineligibleHeader('plan_inactive', 'The assigned commission plan is not active.', $referral, $assignment, $plan);
        }

        if (! $plan->isCurrentlyEffective($postingDate)) {
            return $this->ineligibleHeader('plan_outside_effective_date', 'The commission plan is outside its effective date range.', $referral, $assignment, $plan);
        }

        return [
            'eligible' => true,
            'reason_code' => null,
            'reason_message' => null,
            'customer_referral' => $referral,
            'assignment' => $assignment,
            'plan' => $plan,
        ];
    }

    /**
     * @return array{eligible: bool, reason_code: string|null, reason_message: string|null, matched_tier: ReferralCommissionPlanTier|null}
     */
    public function evaluateLine(PostedSalesInvoiceLine $line, ReferralCommissionPlan $plan, string $basisAmount): array
    {
        if ((float) $line->quantity <= 0) {
            return $this->ineligibleLine('quantity_not_positive', 'Invoice line quantity is not positive.');
        }

        if ($plan->minimum_eligible_amount !== null && DecimalMath::compare($basisAmount, $plan->minimum_eligible_amount) < 0) {
            return $this->ineligibleLine('minimum_amount_not_met', 'Invoice line does not meet the minimum eligible amount.');
        }

        if (! $this->lineMatchesScope($line, $plan)) {
            return $this->ineligibleLine('rule_missing', 'Invoice line does not match the commission plan scope.');
        }

        return [
            'eligible' => true,
            'reason_code' => null,
            'reason_message' => null,
            'matched_tier' => $this->resolveTier($plan, $basisAmount),
        ];
    }

    private function lineMatchesScope(PostedSalesInvoiceLine $line, ReferralCommissionPlan $plan): bool
    {
        return match ($plan->commission_scope) {
            ReferralCommissionScope::ALL_ELIGIBLE_SALES => true,
            ReferralCommissionScope::SPECIFIC_ITEMS => $plan->eligibleItems
                ->where('is_included', true)
                ->pluck('item_id')
                ->contains((int) $line->item_id),
            ReferralCommissionScope::SPECIFIC_CATEGORIES => $line->item?->item_category_id !== null
                && $plan->eligibleCategories
                    ->where('is_included', true)
                    ->pluck('category_id')
                    ->contains((int) $line->item->item_category_id),
            ReferralCommissionScope::SPECIFIC_ITEMS_AND_CATEGORIES => $plan->eligibleItems
                ->where('is_included', true)
                ->pluck('item_id')
                ->contains((int) $line->item_id)
                || ($line->item?->item_category_id !== null && $plan->eligibleCategories
                    ->where('is_included', true)
                    ->pluck('category_id')
                    ->contains((int) $line->item->item_category_id)),
        };
    }

    private function resolveTier(ReferralCommissionPlan $plan, string $basisAmount): ?ReferralCommissionPlanTier
    {
        if (! $plan->commission_method->isTiered()) {
            return null;
        }

        $matches = $plan->tiers
            ->filter(function (ReferralCommissionPlanTier $tier) use ($basisAmount): bool {
                $minimumMet = DecimalMath::compare($basisAmount, $tier->minimum_threshold) >= 0;
                $maximumMet = $tier->maximum_threshold === null || DecimalMath::compare($basisAmount, $tier->maximum_threshold) <= 0;

                return $minimumMet && $maximumMet;
            })
            ->values();

        if ($matches->count() > 1) {
            throw new \RuntimeException('Ambiguous commission tier configuration: multiple tiers match the same basis amount.');
        }

        return $matches->first();
    }

    private function ineligibleHeader(string $code, string $message, ?CustomerReferral $referral = null, ?ReferrerCommissionPlanAssignment $assignment = null, ?ReferralCommissionPlan $plan = null): array
    {
        return [
            'eligible' => false,
            'reason_code' => $code,
            'reason_message' => $message,
            'customer_referral' => $referral,
            'assignment' => $assignment,
            'plan' => $plan,
        ];
    }

    private function ineligibleLine(string $code, string $message): array
    {
        return [
            'eligible' => false,
            'reason_code' => $code,
            'reason_message' => $message,
            'matched_tier' => null,
        ];
    }
}
