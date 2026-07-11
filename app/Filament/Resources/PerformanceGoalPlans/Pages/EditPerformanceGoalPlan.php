<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoalPlans\Pages;

use App\Filament\Resources\PerformanceGoalPlans\PerformanceGoalPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceGoalPlan extends EditRecord
{
    protected static string $resource = PerformanceGoalPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
