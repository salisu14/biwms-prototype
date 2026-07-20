<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceImprovementPlans\Schemas;

use App\Models\PerformanceImprovementPlan;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceImprovementPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::infolist($schema, PerformanceImprovementPlan::class);
    }
}
