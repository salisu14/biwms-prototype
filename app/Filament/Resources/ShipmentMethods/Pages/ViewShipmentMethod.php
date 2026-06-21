<?php

namespace App\Filament\Resources\ShipmentMethods\Pages;

use App\Filament\Resources\ShipmentMethods\ShipmentMethodResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewShipmentMethod extends ViewRecord
{
    protected static string $resource = ShipmentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
