<?php

namespace App\Filament\Resources\InventoryPostingGroups\Pages;

use App\Filament\Resources\InventoryPostingGroups\InventoryPostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryPostingGroups extends ListRecords
{
    protected static string $resource = InventoryPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
