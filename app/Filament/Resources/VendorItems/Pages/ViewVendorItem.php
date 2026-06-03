<?php

namespace App\Filament\Resources\VendorItems\Pages;

use App\Filament\Resources\VendorItems\VendorItemResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorItem extends ViewRecord
{
    protected static string $resource = VendorItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
