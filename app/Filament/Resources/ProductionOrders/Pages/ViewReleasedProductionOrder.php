<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Pages\BaseViewRecord;
use App\Filament\Resources\ProductionOrders\Actions\ProductionOrderActions;
use App\Filament\Resources\ProductionOrders\ReleasedProductionOrderResource;
use Filament\Actions\EditAction;

class ViewReleasedProductionOrder extends BaseViewRecord
{
    protected static string $resource = ReleasedProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductionOrderActions::postOutput(),
            ProductionOrderActions::finish(),
            ProductionOrderActions::cancel(),
            ProductionOrderActions::reopen(),
            EditAction::make(),
        ];
    }
}
