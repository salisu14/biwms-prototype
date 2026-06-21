<?php

namespace App\Filament\Resources\WarehouseReceipts\Pages;

use App\Filament\Resources\WarehouseReceipts\WarehouseReceiptResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouseReceipt extends ViewRecord
{
    protected static string $resource = WarehouseReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
