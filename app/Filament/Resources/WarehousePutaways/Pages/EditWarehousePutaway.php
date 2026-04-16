<?php

namespace App\Filament\Resources\WarehousePutaways\Pages;

use App\Filament\Resources\WarehousePutaways\WarehousePutawayResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWarehousePutaway extends EditRecord
{
    protected static string $resource = WarehousePutawayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
