<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewPanels\Tables;

use App\Models\RecruitmentInterviewPanel;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentInterviewPanelsTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, RecruitmentInterviewPanel::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
