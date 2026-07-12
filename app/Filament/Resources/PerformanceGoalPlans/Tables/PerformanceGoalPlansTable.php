<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoalPlans\Tables;

use App\Models\PerformanceGoalPlan;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class PerformanceGoalPlansTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, PerformanceGoalPlan::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
