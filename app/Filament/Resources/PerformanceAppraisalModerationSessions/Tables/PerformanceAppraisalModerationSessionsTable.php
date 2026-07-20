<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalModerationSessions\Tables;

use App\Models\PerformanceAppraisalModerationSession;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformanceAppraisalModerationSessionsTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformanceAppraisalModerationSession::class);
    }
}
