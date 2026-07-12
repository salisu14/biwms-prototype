<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentAssessments\Tables;

use App\Models\RecruitmentAssessment;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentAssessmentsTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, RecruitmentAssessment::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
