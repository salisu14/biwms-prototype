<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoalPlans\Pages;

use App\Filament\Resources\PerformanceGoalPlans\PerformanceGoalPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceGoalPlans extends ListRecords
{
    protected static string $resource = PerformanceGoalPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
