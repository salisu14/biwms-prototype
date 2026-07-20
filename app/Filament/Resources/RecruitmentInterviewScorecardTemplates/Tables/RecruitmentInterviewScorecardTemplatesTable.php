<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Tables;

use App\Models\RecruitmentInterviewScorecardTemplate;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentInterviewScorecardTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return RecruitmentResourceSchema::table($table, RecruitmentInterviewScorecardTemplate::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
