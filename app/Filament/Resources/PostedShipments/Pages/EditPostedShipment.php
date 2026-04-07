<?php

namespace App\Filament\Resources\PostedShipments\Pages;

use App\Filament\Resources\PostedShipments\PostedShipmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPostedShipment extends EditRecord
{
    protected static string $resource = PostedShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
