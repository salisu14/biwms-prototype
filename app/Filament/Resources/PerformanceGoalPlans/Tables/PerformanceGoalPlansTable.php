<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoalPlans\Tables;

use App\Models\PerformanceGoalPlan;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformanceGoalPlansTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformanceGoalPlan::class);
    }
}
