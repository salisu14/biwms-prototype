<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingPlans\Pages;

use App\Filament\Resources\RecruitmentOnboardingPlans\RecruitmentOnboardingPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentOnboardingPlan extends CreateRecord
{
    protected static string $resource = RecruitmentOnboardingPlanResource::class;
}
