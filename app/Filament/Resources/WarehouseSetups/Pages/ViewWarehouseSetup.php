<?php

namespace App\Filament\Resources\WarehouseSetups\Pages;

use App\Filament\Resources\WarehouseSetups\WarehouseSetupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouseSetup extends ViewRecord
{
    protected static string $resource = WarehouseSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
