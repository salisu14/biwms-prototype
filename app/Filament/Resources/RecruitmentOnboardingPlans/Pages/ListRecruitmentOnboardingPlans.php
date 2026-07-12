<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingPlans\Pages;

use App\Filament\Resources\RecruitmentOnboardingPlans\RecruitmentOnboardingPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentOnboardingPlans extends ListRecords
{
    protected static string $resource = RecruitmentOnboardingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
