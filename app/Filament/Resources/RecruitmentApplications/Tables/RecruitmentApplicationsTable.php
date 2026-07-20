<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplications\Tables;

use App\Models\RecruitmentApplication;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return RecruitmentResourceSchema::table($table, RecruitmentApplication::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
