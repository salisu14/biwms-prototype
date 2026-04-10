<?php

namespace App\Filament\Resources\BlanketPurchaseOrders\Pages;

use App\Filament\Resources\BlanketPurchaseOrders\BlanketPurchaseOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlanketPurchaseOrders extends ListRecords
{
    protected static string $resource = BlanketPurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
