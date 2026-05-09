<?php

namespace App\Filament\Resources\WarehouseSetups\Pages;

use App\Filament\Resources\WarehouseSetups\WarehouseSetupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseSetups extends ListRecords
{
    protected static string $resource = WarehouseSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
