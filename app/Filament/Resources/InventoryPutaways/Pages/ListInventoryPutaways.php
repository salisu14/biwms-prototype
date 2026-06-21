<?php

namespace App\Filament\Resources\InventoryPutaways\Pages;

use App\Filament\Resources\InventoryPutaways\InventoryPutawayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryPutaways extends ListRecords
{
    protected static string $resource = InventoryPutawayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
