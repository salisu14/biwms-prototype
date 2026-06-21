<?php

namespace App\Filament\Resources\WarehouseReceipts\Pages;

use App\Filament\Resources\WarehouseReceipts\WarehouseReceiptResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseReceipt extends EditRecord
{
    protected static string $resource = WarehouseReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
