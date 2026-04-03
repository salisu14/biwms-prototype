<?php

namespace App\Filament\Resources\ProductionBomVersions\Pages;

use App\Filament\Resources\ProductionBomVersions\ProductionBomVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionBomVersions extends ListRecords
{
    protected static string $resource = ProductionBomVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
