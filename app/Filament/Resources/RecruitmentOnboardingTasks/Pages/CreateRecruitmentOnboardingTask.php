<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTasks\Pages;

use App\Filament\Resources\RecruitmentOnboardingTasks\RecruitmentOnboardingTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentOnboardingTask extends CreateRecord
{
    protected static string $resource = RecruitmentOnboardingTaskResource::class;
}
