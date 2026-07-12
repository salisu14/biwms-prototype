<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingPlans\Pages;

use App\Filament\Resources\RecruitmentOnboardingPlans\RecruitmentOnboardingPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentOnboardingPlan extends EditRecord
{
    protected static string $resource = RecruitmentOnboardingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
