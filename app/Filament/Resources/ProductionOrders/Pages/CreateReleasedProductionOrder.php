<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Resources\ProductionOrders\ReleasedProductionOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReleasedProductionOrder extends CreateRecord
{
    protected static string $resource = ReleasedProductionOrderResource::class;
}
