<?php

namespace App\Filament\Resources\ItemTrackingCodes\Pages;

use App\Filament\Resources\ItemTrackingCodes\ItemTrackingCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemTrackingCodes extends ListRecords
{
    protected static string $resource = ItemTrackingCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
