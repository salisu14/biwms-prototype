<?php

namespace App\Filament\Resources\ProductionBomVersions\Pages;

use App\Filament\Resources\ProductionBomVersions\ProductionBomVersionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionBomVersion extends ViewRecord
{
    protected static string $resource = ProductionBomVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
