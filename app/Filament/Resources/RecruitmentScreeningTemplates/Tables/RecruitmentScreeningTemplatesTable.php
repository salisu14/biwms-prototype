<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentScreeningTemplates\Tables;

use App\Models\RecruitmentScreeningTemplate;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentScreeningTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return RecruitmentResourceSchema::table($table, RecruitmentScreeningTemplate::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
