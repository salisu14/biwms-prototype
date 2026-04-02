<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

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
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
