<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Resources\ProductionOrders\FinishedProductionOrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFinishedProductionOrder extends ViewRecord
{
    protected static string $resource = FinishedProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
