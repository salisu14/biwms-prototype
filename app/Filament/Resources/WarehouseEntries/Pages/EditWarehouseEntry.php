<?php

namespace App\Filament\Resources\WarehouseEntries\Pages;

use App\Filament\Resources\WarehouseEntries\WarehouseEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseEntry extends EditRecord
{
    protected static string $resource = WarehouseEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
