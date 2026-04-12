<?php

namespace App\Filament\Resources\WarehouseEntries\Pages;

use App\Filament\Resources\WarehouseEntries\WarehouseEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouseEntry extends ViewRecord
{
    protected static string $resource = WarehouseEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
