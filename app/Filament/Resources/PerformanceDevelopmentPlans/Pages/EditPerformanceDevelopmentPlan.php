<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceDevelopmentPlans\Pages;

use App\Filament\Resources\PerformanceDevelopmentPlans\PerformanceDevelopmentPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceDevelopmentPlan extends EditRecord
{
    protected static string $resource = PerformanceDevelopmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
