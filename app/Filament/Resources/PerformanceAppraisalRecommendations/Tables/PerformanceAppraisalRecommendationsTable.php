<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalRecommendations\Tables;

use App\Models\PerformanceAppraisalRecommendation;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformanceAppraisalRecommendationsTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformanceAppraisalRecommendation::class);
    }
}
