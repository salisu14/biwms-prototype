<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingPlans\Schemas;

use App\Models\RecruitmentOnboardingPlan;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentOnboardingPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentOnboardingPlan::class);
    }
}
