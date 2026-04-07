<?php

namespace App\Filament\Resources\SalesShipmentHeaders\Pages;

use App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesShipmentHeaders extends ListRecords
{
    protected static string $resource = SalesShipmentHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
