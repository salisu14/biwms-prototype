<?php

namespace App\Filament\Resources\BlanketSalesOrders\Pages;

use App\Filament\Resources\BlanketSalesOrders\BlanketSalesOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlanketSalesOrders extends ListRecords
{
    protected static string $resource = BlanketSalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
