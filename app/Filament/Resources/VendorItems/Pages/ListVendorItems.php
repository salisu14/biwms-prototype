<?php

namespace App\Filament\Resources\VendorItems\Pages;

use App\Filament\Resources\VendorItems\VendorItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorItems extends ListRecords
{
    protected static string $resource = VendorItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
