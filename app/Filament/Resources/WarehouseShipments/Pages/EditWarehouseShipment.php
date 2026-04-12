<?php

namespace App\Filament\Resources\WarehouseShipments\Pages;

use App\Filament\Resources\WarehouseShipments\WarehouseShipmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseShipment extends EditRecord
{
    protected static string $resource = WarehouseShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
