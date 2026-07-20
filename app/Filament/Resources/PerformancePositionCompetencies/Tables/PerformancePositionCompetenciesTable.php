<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformancePositionCompetencies\Tables;

use App\Models\PerformancePositionCompetency;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformancePositionCompetenciesTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformancePositionCompetency::class);
    }
}
