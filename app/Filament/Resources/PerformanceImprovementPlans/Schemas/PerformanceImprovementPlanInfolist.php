<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceImprovementPlans\Schemas;

use App\Models\PerformanceImprovementPlan;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceImprovementPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, PerformanceImprovementPlan::class);
    }
}
