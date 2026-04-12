<?php

namespace App\Filament\Resources\WarehouseShipments\Pages;

use App\Filament\Resources\WarehouseShipments\WarehouseShipmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouseShipment extends ViewRecord
{
    protected static string $resource = WarehouseShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
