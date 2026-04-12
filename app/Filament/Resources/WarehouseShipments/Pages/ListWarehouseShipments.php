<?php

namespace App\Filament\Resources\WarehouseShipments\Pages;

use App\Filament\Resources\WarehouseShipments\WarehouseShipmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseShipments extends ListRecords
{
    protected static string $resource = WarehouseShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
