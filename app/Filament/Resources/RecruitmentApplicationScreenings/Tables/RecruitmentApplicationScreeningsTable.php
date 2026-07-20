<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplicationScreenings\Tables;

use App\Models\RecruitmentApplicationScreening;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentApplicationScreeningsTable
{
    public static function configure(Table $table): Table
    {
        return RecruitmentResourceSchema::table($table, RecruitmentApplicationScreening::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
