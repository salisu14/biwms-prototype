<?php

namespace App\Filament\Resources\WarehousePutaways\Pages;

use App\Filament\Resources\WarehousePutaways\WarehousePutawayResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehousePutaway extends ViewRecord
{
    protected static string $resource = WarehousePutawayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
