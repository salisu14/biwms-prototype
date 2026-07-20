<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisals\Tables;

use App\Models\PerformanceAppraisal;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformanceAppraisalsTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformanceAppraisal::class);
    }
}
