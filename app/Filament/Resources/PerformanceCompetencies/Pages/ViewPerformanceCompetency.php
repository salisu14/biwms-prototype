<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencies\Pages;

use App\Filament\Resources\PerformanceCompetencies\PerformanceCompetencyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceCompetency extends ViewRecord
{
    protected static string $resource = PerformanceCompetencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
