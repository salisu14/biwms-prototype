<?php

namespace App\Filament\Resources\ItemLots\Pages;

use App\Filament\Resources\ItemLots\ItemLotResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemLot extends ViewRecord
{
    protected static string $resource = ItemLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
