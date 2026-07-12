<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceImprovementPlans\Tables;

use App\Models\PerformanceImprovementPlan;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class PerformanceImprovementPlansTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, PerformanceImprovementPlan::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
