<?php

namespace App\Filament\Resources\ShipmentMethods\Pages;

use App\Filament\Resources\ShipmentMethods\ShipmentMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditShipmentMethod extends EditRecord
{
    protected static string $resource = ShipmentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
