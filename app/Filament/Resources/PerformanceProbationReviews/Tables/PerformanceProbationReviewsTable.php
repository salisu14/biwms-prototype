<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceProbationReviews\Tables;

use App\Models\PerformanceProbationReview;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformanceProbationReviewsTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformanceProbationReview::class);
    }
}
