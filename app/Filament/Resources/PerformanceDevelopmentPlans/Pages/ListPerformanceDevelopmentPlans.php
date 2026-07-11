<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceDevelopmentPlans\Pages;

use App\Filament\Resources\PerformanceDevelopmentPlans\PerformanceDevelopmentPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceDevelopmentPlans extends ListRecords
{
    protected static string $resource = PerformanceDevelopmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
