<?php

namespace App\Filament\Resources\WarehouseActivities\Pages;

use App\Filament\Resources\WarehouseActivities\WarehouseActivityResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouseActivity extends ViewRecord
{
    protected static string $resource = WarehouseActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
