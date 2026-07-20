<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentVacancies\Tables;

use App\Models\RecruitmentVacancy;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentVacanciesTable
{
    public static function configure(Table $table): Table
    {
        return RecruitmentResourceSchema::table($table, RecruitmentVacancy::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
