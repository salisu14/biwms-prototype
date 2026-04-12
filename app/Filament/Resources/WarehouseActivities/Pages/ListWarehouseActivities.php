<?php

namespace App\Filament\Resources\WarehouseActivities\Pages;

use App\Filament\Resources\WarehouseActivities\WarehouseActivityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseActivities extends ListRecords
{
    protected static string $resource = WarehouseActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
