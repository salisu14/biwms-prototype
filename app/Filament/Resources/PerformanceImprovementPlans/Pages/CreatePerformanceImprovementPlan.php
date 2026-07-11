<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceImprovementPlans\Pages;

use App\Filament\Resources\PerformanceImprovementPlans\PerformanceImprovementPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceImprovementPlan extends CreateRecord
{
    protected static string $resource = PerformanceImprovementPlanResource::class;
}
