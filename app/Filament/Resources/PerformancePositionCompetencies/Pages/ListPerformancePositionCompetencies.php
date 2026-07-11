<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformancePositionCompetencies\Pages;

use App\Filament\Resources\PerformancePositionCompetencies\PerformancePositionCompetencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformancePositionCompetencies extends ListRecords
{
    protected static string $resource = PerformancePositionCompetencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
