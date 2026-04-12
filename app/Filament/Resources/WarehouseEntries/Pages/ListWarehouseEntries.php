<?php

namespace App\Filament\Resources\WarehouseEntries\Pages;

use App\Filament\Resources\WarehouseEntries\WarehouseEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseEntries extends ListRecords
{
    protected static string $resource = WarehouseEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
