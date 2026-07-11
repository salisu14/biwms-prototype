<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceImprovementPlans\Pages;

use App\Filament\Resources\PerformanceImprovementPlans\PerformanceImprovementPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceImprovementPlan extends EditRecord
{
    protected static string $resource = PerformanceImprovementPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
