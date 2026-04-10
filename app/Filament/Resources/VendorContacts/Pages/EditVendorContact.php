<?php

namespace App\Filament\Resources\VendorContacts\Pages;

use App\Filament\Resources\VendorContacts\VendorContactResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVendorContact extends EditRecord
{
    protected static string $resource = VendorContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
