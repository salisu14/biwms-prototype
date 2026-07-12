<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformancePositionCompetencies\Tables;

use App\Models\PerformancePositionCompetency;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class PerformancePositionCompetenciesTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, PerformancePositionCompetency::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
