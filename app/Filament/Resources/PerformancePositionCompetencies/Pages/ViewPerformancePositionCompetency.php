<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformancePositionCompetencies\Pages;

use App\Filament\Resources\PerformancePositionCompetencies\PerformancePositionCompetencyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformancePositionCompetency extends ViewRecord
{
    protected static string $resource = PerformancePositionCompetencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
