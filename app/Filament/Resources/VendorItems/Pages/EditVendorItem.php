<?php

namespace App\Filament\Resources\VendorItems\Pages;

use App\Filament\Resources\VendorItems\VendorItemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVendorItem extends EditRecord
{
    protected static string $resource = VendorItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
