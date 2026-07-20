<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoals\Tables;

use App\Models\PerformanceGoal;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformanceGoalsTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformanceGoal::class);
    }
}
