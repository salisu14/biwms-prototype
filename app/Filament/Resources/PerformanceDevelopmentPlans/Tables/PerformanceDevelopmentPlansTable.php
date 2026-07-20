<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceDevelopmentPlans\Tables;

use App\Models\PerformanceDevelopmentPlan;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformanceDevelopmentPlansTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformanceDevelopmentPlan::class);
    }
}
