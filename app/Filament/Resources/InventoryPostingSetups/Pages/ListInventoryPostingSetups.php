<?php

namespace App\Filament\Resources\InventoryPostingSetups\Pages;

use App\Filament\Resources\InventoryPostingSetups\InventoryPostingSetupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryPostingSetups extends ListRecords
{
    protected static string $resource = InventoryPostingSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
