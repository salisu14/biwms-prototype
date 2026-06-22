<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Pages\BaseViewRecord;
use App\Filament\Resources\ProductionOrders\FinishedProductionOrderResource;

class ViewFinishedProductionOrder extends BaseViewRecord
{
    protected static string $resource = FinishedProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
