<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferralCommissionPlans\Pages;

use App\Filament\Resources\ReferralCommissionPlans\Pages\CreateReferralCommissionPlan as BaseCreateReferralCommissionPlan;
use App\Filament\Sales\Resources\ReferralCommissionPlans\ReferralCommissionPlanResource;

class CreateReferralCommissionPlan extends BaseCreateReferralCommissionPlan
{
    protected static string $resource = ReferralCommissionPlanResource::class;
}
