<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencyFrameworks\Pages;

use App\Filament\Resources\PerformanceCompetencyFrameworks\PerformanceCompetencyFrameworkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceCompetencyFrameworks extends ListRecords
{
    protected static string $resource = PerformanceCompetencyFrameworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
