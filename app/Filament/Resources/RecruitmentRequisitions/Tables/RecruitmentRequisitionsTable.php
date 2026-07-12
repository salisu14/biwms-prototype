<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentRequisitions\Tables;

use App\Models\RecruitmentRequisition;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentRequisitionsTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, RecruitmentRequisition::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
