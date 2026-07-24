<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReferralCommissionAssignmentStatus;
use App\Enums\ReferralCommissionMethod;
use App\Enums\ReferralCommissionPlanStatus;
use App\Enums\ReferralCommissionScope;
use App\Models\ReferralCommissionPlan;
use App\Models\ReferralCommissionSetting;
use App\Models\ReferrerCommissionPlanAssignment;
use App\Services\Sales\ReferralCommissions\ReferralCommissionPlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BiwmsReferralCommissionConfigReconcile extends Command
{
    protected $signature = 'biwms:referral-commission-config-reconcile {--details} {--business=} {--plan=} {--referrer=} {--export=}';

    protected $description = 'Report referral commission configuration inconsistencies without mutating data.';

    public function handle(ReferralCommissionPlanService $planService): int
    {
        $findings = $this->findings($planService);

        $this->info('BIWMS Referral Commission Config Reconcile');
        $this->line('Findings: '.$findings->count());

        if ($this->option('details')) {
            $findings->each(function (array $finding): void {
                $this->line(sprintf(
                    '- [%s] %s: %s',
                    $finding['severity'],
                    $finding['classification'],
                    $finding['message'],
                ));
            });
        }

        if ($export = $this->option('export')) {
            File::ensureDirectoryExists(dirname((string) $export));
            File::put((string) $export, json_encode([
                'generated_at' => now()->toISOString(),
                'findings' => $findings->values(),
            ], JSON_PRETTY_PRINT));
            $this->info("Exported referral commission configuration findings to {$export}");
        }

        return self::SUCCESS;
    }

    private function findings(ReferralCommissionPlanService $planService)
    {
        $findings = collect();

        ReferralCommissionSetting::query()
            ->with(['business', 'defaultPlan'])
            ->when($this->option('business'), fn ($query, $businessId) => $query->where('business_id', $businessId))
            ->get()
            ->each(function (ReferralCommissionSetting $setting) use ($findings): void {
                if ($setting->commission_decimal_places < 0 || $setting->commission_decimal_places > 6) {
                    $findings->push($this->finding('invalid_decimal_places', 'critical', "Setting {$setting->id} has invalid decimal places."));
                }

                if ($setting->minimum_eligible_sale_amount !== null && (float) $setting->minimum_eligible_sale_amount < 0) {
                    $findings->push($this->finding('negative_minimum_eligible_sale_amount', 'critical', "Setting {$setting->id} has a negative minimum eligible amount."));
                }

                if ($setting->defaultPlan) {
                    if ($setting->defaultPlan->business_id !== null && (int) $setting->defaultPlan->business_id !== (int) $setting->business_id) {
                        $findings->push($this->finding('default_plan_cross_business', 'critical', "Setting {$setting->id} default plan belongs to another business."));
                    }

                    if ($setting->defaultPlan->status !== ReferralCommissionPlanStatus::ACTIVE) {
                        $findings->push($this->finding('default_plan_not_active', 'warning', "Setting {$setting->id} default plan is not active."));
                    }
                }

                if ($setting->is_enabled && ! $setting->require_plan_assignment && $setting->default_plan_id === null) {
                    $findings->push($this->finding('enabled_without_assignment_strategy', 'critical', "Business {$setting->business_id} has commissions enabled without a default plan or required assignments."));
                }
            });

        ReferralCommissionPlan::query()
            ->with(['tiers', 'eligibleItems', 'eligibleCategories'])
            ->when($this->option('business'), fn ($query, $businessId) => $query->where('business_id', $businessId))
            ->when($this->option('plan'), fn ($query, $planId) => $query->whereKey($planId))
            ->get()
            ->each(function (ReferralCommissionPlan $plan) use ($findings, $planService): void {
                try {
                    $planService->validateForActivation($plan);
                } catch (\Throwable $exception) {
                    if ($plan->status === ReferralCommissionPlanStatus::ACTIVE) {
                        $findings->push($this->finding('active_plan_invalid', 'critical', "{$plan->code}: {$exception->getMessage()}"));
                    }
                }

                if ($plan->status === ReferralCommissionPlanStatus::ACTIVE && $plan->effective_to !== null && $plan->effective_to->isPast()) {
                    $findings->push($this->finding('expired_plan_still_active', 'warning', "{$plan->code} is expired but still active."));
                }

                if ($plan->commission_method === ReferralCommissionMethod::PERCENTAGE && $plan->percentage_rate === null) {
                    $findings->push($this->finding('percentage_plan_missing_rate', 'critical', "{$plan->code} is missing percentage rate."));
                }

                if ($plan->commission_method === ReferralCommissionMethod::FIXED_AMOUNT && $plan->fixed_amount === null) {
                    $findings->push($this->finding('fixed_plan_missing_amount', 'critical', "{$plan->code} is missing fixed amount."));
                }

                if ($plan->commission_method->isTiered() && $plan->tiers->isEmpty()) {
                    $findings->push($this->finding('tiered_plan_without_tiers', 'critical', "{$plan->code} is tiered but has no tiers."));
                }

                if ($plan->commission_scope === ReferralCommissionScope::SPECIFIC_ITEMS && ! $plan->eligibleItems->contains(fn ($row): bool => $row->is_included)) {
                    $findings->push($this->finding('item_scoped_plan_without_items', 'critical', "{$plan->code} requires eligible items."));
                }

                if ($plan->commission_scope === ReferralCommissionScope::SPECIFIC_CATEGORIES && ! $plan->eligibleCategories->contains(fn ($row): bool => $row->is_included)) {
                    $findings->push($this->finding('category_scoped_plan_without_categories', 'critical', "{$plan->code} requires eligible categories."));
                }
            });

        ReferrerCommissionPlanAssignment::query()
            ->with(['referrer', 'plan'])
            ->when($this->option('business'), fn ($query, $businessId) => $query->where('business_id', $businessId))
            ->when($this->option('referrer'), fn ($query, $referrerId) => $query->where('referrer_id', $referrerId))
            ->get()
            ->each(function (ReferrerCommissionPlanAssignment $assignment) use ($findings): void {
                if ($assignment->status === ReferralCommissionAssignmentStatus::ACTIVE && ! $assignment->referrer?->is_active) {
                    $findings->push($this->finding('assignment_inactive_referrer', 'critical', "Assignment {$assignment->id} is linked to inactive referrer."));
                }

                if ($assignment->status === ReferralCommissionAssignmentStatus::ACTIVE && ! $assignment->referrer?->commission_eligible) {
                    $findings->push($this->finding('assignment_commission_ineligible_referrer', 'critical', "Assignment {$assignment->id} is linked to a commission-ineligible referrer."));
                }

                if ($assignment->status === ReferralCommissionAssignmentStatus::ACTIVE && $assignment->plan?->status !== ReferralCommissionPlanStatus::ACTIVE) {
                    $findings->push($this->finding('assignment_inactive_plan', 'critical', "Assignment {$assignment->id} is linked to an inactive plan."));
                }

                if ($assignment->status === ReferralCommissionAssignmentStatus::ENDED && $assignment->effective_to === null) {
                    $findings->push($this->finding('ended_assignment_without_effective_to', 'warning', "Assignment {$assignment->id} is ended without effective_to."));
                }

                if ($assignment->referrer?->business_id !== null && $assignment->plan?->business_id !== null && (int) $assignment->referrer->business_id !== (int) $assignment->plan->business_id) {
                    $findings->push($this->finding('assignment_cross_business', 'critical', "Assignment {$assignment->id} links referrer and plan across businesses."));
                }
            });

        ReferrerCommissionPlanAssignment::query()
            ->selectRaw('referrer_id, count(*) as open_count')
            ->where('status', ReferralCommissionAssignmentStatus::ACTIVE)
            ->where('is_primary', true)
            ->whereNull('effective_to')
            ->groupBy('referrer_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->each(fn ($row) => $findings->push($this->finding('multiple_open_active_primary_assignments', 'critical', "Referrer {$row->referrer_id} has {$row->open_count} open active primary assignments.")));

        ReferralCommissionPlan::query()
            ->selectRaw('business_id, count(*) as default_count')
            ->where('is_default', true)
            ->groupBy('business_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->each(fn ($row) => $findings->push($this->finding('multiple_default_plans', 'warning', "Business {$row->business_id} has {$row->default_count} default plans.")));

        return $findings;
    }

    private function finding(string $classification, string $severity, string $message): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            'message' => $message,
            'suggested_remediation' => 'Review referral commission configuration and correct the setup record. This command is report-only and does not apply fixes.',
        ];
    }
}
