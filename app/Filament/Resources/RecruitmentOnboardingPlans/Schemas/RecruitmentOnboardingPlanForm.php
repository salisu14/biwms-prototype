<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingPlans\Schemas;

use App\Models\RecruitmentOnboardingPlan;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentOnboardingPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, RecruitmentOnboardingPlan::class);
    }
}
