<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferralCommissionPlans\Pages;

use App\Filament\Resources\ReferralCommissionPlans\Pages\ViewReferralCommissionPlan as BaseViewReferralCommissionPlan;
use App\Filament\Sales\Resources\ReferralCommissionPlans\ReferralCommissionPlanResource;

class ViewReferralCommissionPlan extends BaseViewReferralCommissionPlan
{
    protected static string $resource = ReferralCommissionPlanResource::class;
}
