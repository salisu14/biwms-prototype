<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingPlans\Pages;

use App\Filament\Resources\RecruitmentOnboardingPlans\RecruitmentOnboardingPlanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentOnboardingPlan extends ViewRecord
{
    protected static string $resource = RecruitmentOnboardingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
