<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceDevelopmentPlans\Tables;

use App\Models\PerformanceDevelopmentPlan;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class PerformanceDevelopmentPlansTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, PerformanceDevelopmentPlan::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
