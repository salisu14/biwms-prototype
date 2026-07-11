<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceImprovementPlans\Pages;

use App\Filament\Resources\PerformanceImprovementPlans\PerformanceImprovementPlanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceImprovementPlan extends ViewRecord
{
    protected static string $resource = PerformanceImprovementPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
