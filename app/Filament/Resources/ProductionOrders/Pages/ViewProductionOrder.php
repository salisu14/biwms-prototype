<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Resources\ProductionOrders\Actions\ProductionOrderActions;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionOrder extends ViewRecord
{
    protected static string $resource = ProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductionOrderActions::planMultiLevelProduction(),
            ProductionOrderActions::refreshHierarchyStatus(),
            ProductionOrderActions::generateExecutionDependencies(),
            ProductionOrderActions::release(),
            ProductionOrderActions::postOutput(),
            ProductionOrderActions::finish(),
            ProductionOrderActions::calculateExpectedCost(),
            ProductionOrderActions::settleProductionCost(),
            ProductionOrderActions::viewCostReconciliation(),
            ProductionOrderActions::cancel(),
            ProductionOrderActions::reopen(),
            ProductionOrderActions::refresh(),
            EditAction::make(),
        ];
    }
}
