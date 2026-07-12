<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencyFrameworks\Tables;

use App\Models\PerformanceCompetencyFramework;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class PerformanceCompetencyFrameworksTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, PerformanceCompetencyFramework::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
