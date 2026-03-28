<?php

namespace App\Filament\Resources\InventoryPostingSetups\Pages;

use App\Filament\Resources\InventoryPostingSetups\InventoryPostingSetupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryPostingSetup extends EditRecord
{
    protected static string $resource = InventoryPostingSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
