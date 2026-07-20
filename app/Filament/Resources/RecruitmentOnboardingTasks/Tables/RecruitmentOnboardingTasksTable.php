<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTasks\Tables;

use App\Models\RecruitmentOnboardingTask;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentOnboardingTasksTable
{
    public static function configure(Table $table): Table
    {
        return RecruitmentResourceSchema::table($table, RecruitmentOnboardingTask::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
