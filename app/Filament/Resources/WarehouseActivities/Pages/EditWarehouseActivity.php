<?php

namespace App\Filament\Resources\WarehouseActivities\Pages;

use App\Filament\Resources\WarehouseActivities\WarehouseActivityResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseActivity extends EditRecord
{
    protected static string $resource = WarehouseActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
