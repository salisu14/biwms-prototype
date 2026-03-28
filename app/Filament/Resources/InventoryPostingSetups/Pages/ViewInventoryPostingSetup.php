<?php

namespace App\Filament\Resources\InventoryPostingSetups\Pages;

use App\Filament\Resources\InventoryPostingSetups\InventoryPostingSetupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryPostingSetup extends ViewRecord
{
    protected static string $resource = InventoryPostingSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
