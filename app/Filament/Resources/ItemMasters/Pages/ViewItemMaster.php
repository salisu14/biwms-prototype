<?php

namespace App\Filament\Resources\ItemMasters\Pages;

use App\Filament\Resources\ItemMasters\ItemMasterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemMaster extends ViewRecord
{
    protected static string $resource = ItemMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
