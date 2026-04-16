<?php

namespace App\Filament\Resources\InventoryPutaways\Pages;

use App\Filament\Resources\InventoryPutaways\InventoryPutawayResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryPutaway extends ViewRecord
{
    protected static string $resource = InventoryPutawayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
