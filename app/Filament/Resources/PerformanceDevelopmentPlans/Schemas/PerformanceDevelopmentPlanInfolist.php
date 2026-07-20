<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceDevelopmentPlans\Schemas;

use App\Models\PerformanceDevelopmentPlan;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceDevelopmentPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::infolist($schema, PerformanceDevelopmentPlan::class);
    }
}
