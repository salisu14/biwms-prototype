<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoalPlans\Schemas;

use App\Models\PerformanceGoalPlan;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceGoalPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceGoalPlan::class);
    }
}
