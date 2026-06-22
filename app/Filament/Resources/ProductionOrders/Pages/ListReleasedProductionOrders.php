<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Resources\ProductionOrders\ReleasedProductionOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReleasedProductionOrders extends ListRecords
{
    protected static string $resource = ReleasedProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
