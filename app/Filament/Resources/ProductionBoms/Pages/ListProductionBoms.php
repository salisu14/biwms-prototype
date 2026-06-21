<?php

namespace App\Filament\Resources\ProductionBoms\Pages;

use App\Filament\Resources\ProductionBoms\ProductionBomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionBoms extends ListRecords
{
    protected static string $resource = ProductionBomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
