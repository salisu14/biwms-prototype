<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTasks\Schemas;

use App\Models\RecruitmentOnboardingTask;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentOnboardingTaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentOnboardingTask::class);
    }
}
