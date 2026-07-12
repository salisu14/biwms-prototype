<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentHistories\Tables;

use App\Models\RecruitmentHistory;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, RecruitmentHistory::class)
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
