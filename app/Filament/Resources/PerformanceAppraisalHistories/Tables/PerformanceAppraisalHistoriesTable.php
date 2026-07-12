<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalHistories\Tables;

use App\Models\PerformanceAppraisalHistory;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class PerformanceAppraisalHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, PerformanceAppraisalHistory::class)
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
