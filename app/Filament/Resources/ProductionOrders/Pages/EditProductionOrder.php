<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Resources\ProductionOrders\Actions\ProductionOrderActions;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionOrder extends EditRecord
{
    protected static string $resource = ProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductionOrderActions::release(),
            ProductionOrderActions::postOutput(),
            ProductionOrderActions::finish(),
            ProductionOrderActions::cancel(),
            ProductionOrderActions::reopen(),
            ProductionOrderActions::refresh(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
