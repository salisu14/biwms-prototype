<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoalPlans\Pages;

use App\Filament\Resources\PerformanceGoalPlans\PerformanceGoalPlanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceGoalPlan extends ViewRecord
{
    protected static string $resource = PerformanceGoalPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
