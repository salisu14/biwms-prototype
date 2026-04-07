<?php

namespace App\Filament\Resources\SalesShipmentHeaders\Pages;

use App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesShipmentHeader extends EditRecord
{
    protected static string $resource = SalesShipmentHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
