<?php

namespace App\Filament\Resources\VendorContacts\Pages;

use App\Filament\Resources\VendorContacts\VendorContactResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorContact extends ViewRecord
{
    protected static string $resource = VendorContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
