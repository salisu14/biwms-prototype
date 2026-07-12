<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTasks\Schemas;

use App\Models\RecruitmentOnboardingTask;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentOnboardingTaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, RecruitmentOnboardingTask::class);
    }
}
