<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalModerationSessions\Tables;

use App\Models\PerformanceAppraisalModerationSession;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class PerformanceAppraisalModerationSessionsTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, PerformanceAppraisalModerationSession::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
