<?php

namespace App\Filament\Resources\VendorContacts\Pages;

use App\Filament\Resources\VendorContacts\VendorContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorContacts extends ListRecords
{
    protected static string $resource = VendorContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
