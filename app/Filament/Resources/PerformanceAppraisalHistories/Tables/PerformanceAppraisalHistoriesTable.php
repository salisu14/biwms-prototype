<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalHistories\Tables;

use App\Models\PerformanceAppraisalHistory;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformanceAppraisalHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformanceAppraisalHistory::class);
    }
}
