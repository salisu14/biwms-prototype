<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferralCommissionPlans\Pages;

use App\Filament\Resources\ReferralCommissionPlans\Pages\EditReferralCommissionPlan as BaseEditReferralCommissionPlan;
use App\Filament\Sales\Resources\ReferralCommissionPlans\ReferralCommissionPlanResource;

class EditReferralCommissionPlan extends BaseEditReferralCommissionPlan
{
    protected static string $resource = ReferralCommissionPlanResource::class;
}
