<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTasks\Pages;

use App\Filament\Resources\RecruitmentOnboardingTasks\RecruitmentOnboardingTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentOnboardingTasks extends ListRecords
{
    protected static string $resource = RecruitmentOnboardingTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
