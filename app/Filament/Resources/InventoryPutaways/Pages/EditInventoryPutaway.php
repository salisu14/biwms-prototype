<?php

namespace App\Filament\Resources\InventoryPutaways\Pages;

use App\Filament\Resources\InventoryPutaways\InventoryPutawayResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryPutaway extends EditRecord
{
    protected static string $resource = InventoryPutawayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
