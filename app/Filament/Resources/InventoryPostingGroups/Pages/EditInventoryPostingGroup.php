<?php

namespace App\Filament\Resources\InventoryPostingGroups\Pages;

use App\Filament\Resources\InventoryPostingGroups\InventoryPostingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryPostingGroup extends EditRecord
{
    protected static string $resource = InventoryPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
