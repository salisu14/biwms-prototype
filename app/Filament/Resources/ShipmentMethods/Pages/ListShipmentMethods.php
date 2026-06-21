<?php

namespace App\Filament\Resources\ShipmentMethods\Pages;

use App\Filament\Resources\ShipmentMethods\ShipmentMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShipmentMethods extends ListRecords
{
    protected static string $resource = ShipmentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
