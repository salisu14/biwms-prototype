<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoalPlans\Pages;

use App\Filament\Resources\PerformanceGoalPlans\PerformanceGoalPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceGoalPlan extends CreateRecord
{
    protected static string $resource = PerformanceGoalPlanResource::class;
}
