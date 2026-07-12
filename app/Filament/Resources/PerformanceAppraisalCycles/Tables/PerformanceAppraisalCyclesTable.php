<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles\Tables;

use App\Models\PerformanceAppraisalCycle;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class PerformanceAppraisalCyclesTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, PerformanceAppraisalCycle::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
