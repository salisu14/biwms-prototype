<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceDevelopmentPlans\Schemas;

use App\Models\PerformanceDevelopmentPlan;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceDevelopmentPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceDevelopmentPlan::class);
    }
}
