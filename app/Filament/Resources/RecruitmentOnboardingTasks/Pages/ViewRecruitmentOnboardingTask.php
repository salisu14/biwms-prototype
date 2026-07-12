<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTasks\Pages;

use App\Filament\Resources\RecruitmentOnboardingTasks\RecruitmentOnboardingTaskResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentOnboardingTask extends ViewRecord
{
    protected static string $resource = RecruitmentOnboardingTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
