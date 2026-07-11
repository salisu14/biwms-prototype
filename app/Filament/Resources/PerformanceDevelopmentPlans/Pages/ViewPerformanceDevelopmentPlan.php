<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceDevelopmentPlans\Pages;

use App\Filament\Resources\PerformanceDevelopmentPlans\PerformanceDevelopmentPlanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceDevelopmentPlan extends ViewRecord
{
    protected static string $resource = PerformanceDevelopmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
