<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceImprovementPlans\Schemas;

use App\Models\PerformanceImprovementPlan;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceImprovementPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceImprovementPlan::class);
    }
}
