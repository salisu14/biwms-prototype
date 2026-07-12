<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentScreeningTemplates\Tables;

use App\Models\RecruitmentScreeningTemplate;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentScreeningTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, RecruitmentScreeningTemplate::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
