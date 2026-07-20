<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoalPlans\Schemas;

use App\Models\PerformanceGoalPlan;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceGoalPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::infolist($schema, PerformanceGoalPlan::class);
    }
}
