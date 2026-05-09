<?php

namespace App\Filament\Resources\ItemTrackingCodes\Pages;

use App\Filament\Resources\ItemTrackingCodes\ItemTrackingCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemTrackingCode extends EditRecord
{
    protected static string $resource = ItemTrackingCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
