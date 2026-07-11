<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceDevelopmentPlans\Pages;

use App\Filament\Resources\PerformanceDevelopmentPlans\PerformanceDevelopmentPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceDevelopmentPlan extends CreateRecord
{
    protected static string $resource = PerformanceDevelopmentPlanResource::class;
}
