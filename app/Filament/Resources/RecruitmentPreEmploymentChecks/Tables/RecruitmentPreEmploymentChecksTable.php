<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentPreEmploymentChecks\Tables;

use App\Models\RecruitmentPreEmploymentCheck;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentPreEmploymentChecksTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, RecruitmentPreEmploymentCheck::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
