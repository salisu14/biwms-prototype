<?php

namespace App\Filament\Resources\WarehouseSetups\Pages;

use App\Filament\Resources\WarehouseSetups\WarehouseSetupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseSetup extends EditRecord
{
    protected static string $resource = WarehouseSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
