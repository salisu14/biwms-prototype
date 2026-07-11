<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceImprovementPlans\Pages;

use App\Filament\Resources\PerformanceImprovementPlans\PerformanceImprovementPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceImprovementPlans extends ListRecords
{
    protected static string $resource = PerformanceImprovementPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
