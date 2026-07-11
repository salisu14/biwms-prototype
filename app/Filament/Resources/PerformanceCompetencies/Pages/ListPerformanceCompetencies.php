<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencies\Pages;

use App\Filament\Resources\PerformanceCompetencies\PerformanceCompetencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceCompetencies extends ListRecords
{
    protected static string $resource = PerformanceCompetencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
