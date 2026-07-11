<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencyFrameworks\Pages;

use App\Filament\Resources\PerformanceCompetencyFrameworks\PerformanceCompetencyFrameworkResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceCompetencyFramework extends ViewRecord
{
    protected static string $resource = PerformanceCompetencyFrameworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
