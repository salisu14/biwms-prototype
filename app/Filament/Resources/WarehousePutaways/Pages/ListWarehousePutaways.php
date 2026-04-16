<?php

namespace App\Filament\Resources\WarehousePutaways\Pages;

use App\Filament\Resources\WarehousePutaways\WarehousePutawayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehousePutaways extends ListRecords
{
    protected static string $resource = WarehousePutawayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
