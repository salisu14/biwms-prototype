<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingPlans\Tables;

use App\Models\RecruitmentOnboardingPlan;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentOnboardingPlansTable
{
    public static function configure(Table $table): Table
    {
        return RecruitmentResourceSchema::table($table, RecruitmentOnboardingPlan::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
