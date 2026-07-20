<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceImprovementPlans\Tables;

use App\Models\PerformanceImprovementPlan;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformanceImprovementPlansTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformanceImprovementPlan::class);
    }
}
