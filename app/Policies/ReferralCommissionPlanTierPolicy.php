<?php

declare(strict_types=1);

namespace App\Policies;

class ReferralCommissionPlanTierPolicy extends ReferralCommissionPlanPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.referral_commission_plan';
    }

    protected function legacyKey(): string
    {
        return 'referral_commission_plan_tier';
    }
}
