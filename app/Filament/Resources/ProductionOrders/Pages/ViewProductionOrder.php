<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionOrder extends ViewRecord
{
    protected static string $resource = ProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
