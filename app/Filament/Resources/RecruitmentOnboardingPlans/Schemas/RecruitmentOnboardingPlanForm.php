<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingPlans\Schemas;

use App\Models\RecruitmentOnboardingPlan;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentOnboardingPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::form($schema, RecruitmentOnboardingPlan::class);
    }
}
