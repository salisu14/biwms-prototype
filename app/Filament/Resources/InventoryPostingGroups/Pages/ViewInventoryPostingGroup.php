<?php

namespace App\Filament\Resources\InventoryPostingGroups\Pages;

use App\Filament\Resources\InventoryPostingGroups\InventoryPostingGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryPostingGroup extends ViewRecord
{
    protected static string $resource = InventoryPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
